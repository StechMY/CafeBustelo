<?php

namespace App\Services\PaymentGateway;

use App\Exceptions\Qc168OrderRejectedException;
use App\Exceptions\Qc168UncertainSubmissionException;
use App\Models\AdminUser;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class Qc168WithdrawalPayoutService
{
    private const PROVIDER_STATUSES = [
        WithdrawalPayoutAttempt::STATUS_NONE,
        WithdrawalPayoutAttempt::STATUS_PENDING,
        WithdrawalPayoutAttempt::STATUS_PREPARING,
        WithdrawalPayoutAttempt::STATUS_PROCESSING,
        WithdrawalPayoutAttempt::STATUS_COMPLETED,
        WithdrawalPayoutAttempt::STATUS_FAILED,
        WithdrawalPayoutAttempt::STATUS_REJECT,
        WithdrawalPayoutAttempt::STATUS_REFUND,
        WithdrawalPayoutAttempt::STATUS_OVERDUE,
    ];

    public function __construct(
        private readonly Qc168Gateway $gateway,
        private readonly WithdrawService $withdrawService,
        private readonly PaymentGatewayEventRecorder $eventRecorder,
    ) {}

    public function dispatch(
        WithdrawRequest $withdrawRequest,
        string $source = 'qc168_scheduler',
    ): ?WithdrawalPayoutAttempt {
        if (! $this->gateway->isWithdrawalAvailable()) {
            return null;
        }

        $attempt = $this->claimAttempt($withdrawRequest, false, source: $source);

        return $attempt ? $this->submitAttempt($attempt, $source) : null;
    }

    public function retry(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        string $reason,
        ?string $ip = null,
    ): ?WithdrawalPayoutAttempt {
        if (! $this->gateway->isWithdrawalAvailable()) {
            throw new RuntimeException(__('withdraw.gateway_unavailable'));
        }

        $latestAttempt = $withdrawRequest->payoutAttempts()->latest('attempt_no')->first();

        if (! $latestAttempt || ! $latestAttempt->isSafeFinalFailure()) {
            throw new RuntimeException(__('withdraw.retry_not_allowed'));
        }

        $definitiveCreateFailure = $latestAttempt->gateway_status === WithdrawalPayoutAttempt::STATUS_CREATE_FAILED
            && data_get($latestAttempt->provider_meta, 'submission_definitive_failure') === true;

        if (! $definitiveCreateFailure) {
            $this->syncAttempt($latestAttempt, 'filament_retry_check', $adminUser, $reason, $ip);

            $withdrawRequest->refresh();
            $latestAttempt->refresh();

            if ($withdrawRequest->isPayoutCompleted()) {
                return null;
            }

            if (! $latestAttempt->isSafeFinalFailure()) {
                throw new RuntimeException(__('withdraw.retry_not_allowed'));
            }
        }

        $attempt = $this->claimAttempt($withdrawRequest, true, $adminUser, $reason, $ip, 'filament_retry');

        if (! $attempt) {
            return null;
        }

        $this->eventRecorder->recordWithdrawal(
            'withdrawal.gateway_retry',
            $withdrawRequest->fresh(),
            $attempt,
            $adminUser,
            'filament',
            $reason,
            WithdrawalPayoutAttempt::STATUS_CREATE_PENDING,
            array_filter([
                'ip' => $ip,
                'previous_attempt_id' => $latestAttempt->id,
                'previous_merchant_order_id' => $latestAttempt->merchant_order_id,
            ], fn ($value): bool => $value !== null),
        );

        return $this->submitAttempt($attempt, 'filament_retry');
    }

    public function sync(
        WithdrawRequest $withdrawRequest,
        string $source = 'filament',
        ?AdminUser $adminUser = null,
        ?string $reason = null,
        ?string $ip = null,
    ): array {
        $attempt = $withdrawRequest->payoutAttempts()->latest('attempt_no')->first();

        if (! $attempt) {
            throw new RuntimeException(__('withdraw.no_payout_attempt'));
        }

        return $this->syncAttempt($attempt, $source, $adminUser, $reason, $ip);
    }

    public function cancel(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        string $reason,
        ?string $ip = null,
    ): WithdrawRequest {
        $attempt = $withdrawRequest->payoutAttempts()->latest('attempt_no')->first();

        if (! $attempt || ! $attempt->isSafeFinalFailure()) {
            throw new RuntimeException(__('withdraw.cancel_not_safe'));
        }

        $definitiveCreateFailure = $attempt->gateway_status === WithdrawalPayoutAttempt::STATUS_CREATE_FAILED
            && data_get($attempt->provider_meta, 'submission_definitive_failure') === true;

        if (! $definitiveCreateFailure) {
            $this->syncAttempt($attempt, 'filament_cancel_check', $adminUser, $reason, $ip);
            $attempt->refresh();
            $withdrawRequest->refresh();
        }

        if ($withdrawRequest->isPayoutCompleted() || ! $attempt->isSafeFinalFailure()) {
            throw new RuntimeException(__('withdraw.cancel_not_safe'));
        }

        return $this->withdrawService->cancelPayout(
            $withdrawRequest,
            $attempt,
            $adminUser,
            $reason,
            $ip,
        );
    }

    public function syncAttempt(
        WithdrawalPayoutAttempt $attempt,
        string $source,
        ?AdminUser $adminUser = null,
        ?string $reason = null,
        ?string $ip = null,
    ): array {
        $response = $this->gateway->getOrder($attempt->merchant_order_id);
        $payload = data_get($response, 'data');
        if (! is_array($payload)) {
            throw new RuntimeException('QC168 returned an invalid payout inquiry response.');
        }

        $result = $this->processProviderState($attempt, $payload, $source);
        $withdrawRequest = $attempt->withdrawRequest()->firstOrFail();

        $this->eventRecorder->recordWithdrawal(
            'withdrawal.gateway_sync',
            $withdrawRequest,
            $attempt,
            $adminUser,
            $source,
            $reason,
            $this->nullableString($payload['payment_status'] ?? null),
            array_filter(['ip' => $ip, 'result' => $result['result']], fn ($value): bool => $value !== null),
        );

        return $result;
    }

    public function processProviderState(
        WithdrawalPayoutAttempt $attempt,
        array $payload,
        string $source,
    ): array {
        return DB::transaction(function () use ($attempt, $payload, $source): array {
            $lockedAttempt = WithdrawalPayoutAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();
            $withdrawRequest = WithdrawRequest::query()
                ->whereKey($lockedAttempt->withdraw_request_id)
                ->lockForUpdate()
                ->firstOrFail();

            $timeField = str_contains($source, 'callback')
                ? 'gateway_last_callback_at'
                : 'gateway_last_synced_at';
            $withdrawRequest->forceFill([$timeField => now()])->save();

            if ($error = $this->validateProviderState($lockedAttempt, $payload)) {
                $this->eventRecorder->recordWithdrawal(
                    'withdrawal.gateway_'.$error,
                    $withdrawRequest,
                    $lockedAttempt,
                    source: $source,
                    paymentStatus: $this->nullableString($payload['payment_status'] ?? null),
                    payload: $payload,
                    processingResult: $error,
                );

                return ['accepted' => false, 'result' => $error];
            }

            $incomingStatus = strtolower((string) $payload['payment_status']);

            if ($incomingStatus === WithdrawalPayoutAttempt::STATUS_REFUND) {
                if (! $lockedAttempt->isCompleted()) {
                    $lockedAttempt->forceFill([
                        'gateway_status' => WithdrawalPayoutAttempt::STATUS_REFUND,
                        'failure_reason' => $this->nullableString($payload['payment_fail_reason'] ?? null)
                            ?: $lockedAttempt->failure_reason,
                        'provider_response' => $payload,
                        'provider_meta' => array_merge($lockedAttempt->provider_meta ?? [], [
                            'latest_state_source' => $source,
                            'latest_state_received_at' => now()->toAtomString(),
                        ]),
                    ])->save();

                    if ($this->isCurrentAttempt($withdrawRequest, $lockedAttempt)) {
                        $withdrawRequest->forceFill([
                            'payout_status' => WithdrawRequest::PAYOUT_REFUND_REVIEW,
                            'gateway_payment_status' => WithdrawalPayoutAttempt::STATUS_REFUND,
                            'gateway_fail_reason' => $lockedAttempt->failure_reason,
                        ])->save();
                    }
                }

                $this->eventRecorder->recordWithdrawal(
                    'withdrawal.gateway_refund_review',
                    $withdrawRequest,
                    $lockedAttempt,
                    source: $source,
                    paymentStatus: $incomingStatus,
                    payload: $payload,
                    processingResult: 'refund_requires_manual_review',
                );

                return ['accepted' => true, 'result' => 'refund_requires_manual_review'];
            }

            if (! $this->shouldAcceptStatus($lockedAttempt->gateway_status, $incomingStatus)) {
                $this->eventRecorder->recordWithdrawal(
                    $incomingStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED
                        ? 'withdrawal.gateway_duplicate_completed'
                        : 'withdrawal.gateway_stale_status_ignored',
                    $withdrawRequest,
                    $lockedAttempt,
                    source: $source,
                    paymentStatus: $incomingStatus,
                    payload: $payload,
                    processingResult: 'stale_status_ignored',
                );

                return ['accepted' => true, 'result' => 'stale_status_ignored'];
            }

            $updates = [
                'gateway_status' => $incomingStatus,
                'gateway_order_uid' => $this->nullableString($payload['order_uid'] ?? null)
                    ?: $lockedAttempt->gateway_order_uid,
                'gateway_payment_uid' => $this->nullableString($payload['payment_uid'] ?? null)
                    ?: $lockedAttempt->gateway_payment_uid,
                'gateway_final_amount' => array_key_exists('final_amount', $payload)
                    ? $this->normalizeAmount($payload['final_amount'])
                    : $lockedAttempt->gateway_final_amount,
                'failure_reason' => $this->nullableString($payload['payment_fail_reason'] ?? null)
                    ?: $lockedAttempt->failure_reason,
                'provider_response' => $payload,
                'provider_meta' => array_merge($lockedAttempt->provider_meta ?? [], [
                    'latest_state_source' => $source,
                    'latest_state_received_at' => now()->toAtomString(),
                ]),
            ];

            if ($incomingStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED) {
                $updates['completed_at'] = $lockedAttempt->completed_at ?: now();
            } elseif (in_array($incomingStatus, [
                WithdrawalPayoutAttempt::STATUS_FAILED,
                WithdrawalPayoutAttempt::STATUS_REJECT,
                WithdrawalPayoutAttempt::STATUS_OVERDUE,
            ], true)) {
                $updates['failed_at'] = $lockedAttempt->failed_at ?: now();
            }

            $lockedAttempt->forceFill($updates)->save();

            if ($incomingStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED) {
                $finalized = $this->withdrawService->finalizePayout(
                    $withdrawRequest,
                    $lockedAttempt,
                    $source,
                );

                if (! $finalized) {
                    $this->eventRecorder->recordWithdrawal(
                        'withdrawal.gateway_duplicate_completed',
                        $withdrawRequest,
                        $lockedAttempt,
                        source: $source,
                        paymentStatus: $incomingStatus,
                        payload: $payload,
                        processingResult: 'duplicate_completed',
                    );
                }

                return [
                    'accepted' => true,
                    'result' => $finalized ? 'payout_completed' : 'duplicate_completed',
                ];
            }

            $payoutStatus = $this->withdrawalStatus($incomingStatus);

            if ($this->isCurrentAttempt($withdrawRequest, $lockedAttempt)) {
                $withdrawRequest->forceFill([
                    'payout_status' => $payoutStatus,
                    'gateway_payment_status' => $incomingStatus,
                    'gateway_final_amount' => $lockedAttempt->gateway_final_amount,
                    'gateway_fail_reason' => $lockedAttempt->failure_reason,
                ])->save();
            }

            $eventType = match ($incomingStatus) {
                WithdrawalPayoutAttempt::STATUS_FAILED => 'withdrawal.gateway_failed',
                WithdrawalPayoutAttempt::STATUS_REJECT => 'withdrawal.gateway_rejected',
                WithdrawalPayoutAttempt::STATUS_OVERDUE => 'withdrawal.gateway_overdue',
                default => 'withdrawal.gateway_processing',
            };

            $this->eventRecorder->recordWithdrawal(
                $eventType,
                $withdrawRequest,
                $lockedAttempt,
                source: $source,
                paymentStatus: $incomingStatus,
                payload: $payload,
                processingResult: 'status_updated',
            );

            return ['accepted' => true, 'result' => 'status_updated'];
        });
    }

    private function claimAttempt(
        WithdrawRequest $withdrawRequest,
        bool $retry,
        ?AdminUser $adminUser = null,
        ?string $reason = null,
        ?string $ip = null,
        string $source = 'qc168_scheduler',
    ): ?WithdrawalPayoutAttempt {
        return DB::transaction(function () use ($withdrawRequest, $retry, $adminUser, $reason, $ip, $source): ?WithdrawalPayoutAttempt {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isApproved() || $lockedRequest->isPayoutCompleted()) {
                return null;
            }

            if (! in_array($lockedRequest->approval_mode, [
                WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED,
                WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT,
            ], true)) {
                return null;
            }

            if ($retry) {
                if (! in_array($lockedRequest->payout_status, [
                    WithdrawRequest::PAYOUT_CREATE_FAILED,
                    WithdrawRequest::PAYOUT_FAILED,
                    WithdrawRequest::PAYOUT_REJECTED,
                    WithdrawRequest::PAYOUT_OVERDUE,
                ], true)) {
                    return null;
                }
            } elseif ($lockedRequest->payout_status !== WithdrawRequest::PAYOUT_WAITING_RELEASE
                || ! $lockedRequest->gateway_release_at
                || $lockedRequest->gateway_release_at->isFuture()) {
                return null;
            }

            if (! preg_match('/^\d+$/', (string) $lockedRequest->bank_code)) {
                throw new RuntimeException(__('withdraw.bank_not_supported'));
            }

            $attemptNo = ((int) $lockedRequest->payoutAttempts()->max('attempt_no')) + 1;
            $merchantOrderId = 'WD'.$lockedRequest->id.'-A'.$attemptNo.'-'.Str::ulid();

            $attempt = WithdrawalPayoutAttempt::query()->create([
                'withdraw_request_id' => $lockedRequest->id,
                'attempt_no' => $attemptNo,
                'provider' => WithdrawRequest::GATEWAY_QC168,
                'merchant_order_id' => $merchantOrderId,
                'amount' => $lockedRequest->payoutAmount(),
                'currency' => strtolower((string) config('services.qc168.currency', 'vnd')),
                'payment_channel' => (string) (config('services.qc168.withdraw_payment') ?: config('services.qc168.payment')),
                'bank_name_snapshot' => $lockedRequest->bank_name,
                'bank_code_snapshot' => $lockedRequest->bank_code,
                'bank_account_name_snapshot' => $lockedRequest->bank_account_name,
                'bank_account_no_snapshot' => $lockedRequest->bank_account_no,
                'gateway_status' => WithdrawalPayoutAttempt::STATUS_CREATE_PENDING,
                'provider_meta' => array_filter([
                    'claimed_at' => now()->toAtomString(),
                    'claim_source' => $retry ? 'filament_retry' : $source,
                    'actor_admin_user_id' => $adminUser?->id,
                    'reason' => $reason,
                    'ip' => $ip,
                ], fn ($value): bool => $value !== null),
            ]);

            $lockedRequest->forceFill([
                'payout_status' => WithdrawRequest::PAYOUT_SUBMITTED,
                'gateway_current_merchant_order_id' => $merchantOrderId,
                'gateway_payment_status' => WithdrawalPayoutAttempt::STATUS_CREATE_PENDING,
                'gateway_fail_reason' => null,
            ])->save();

            return $attempt;
        });
    }

    private function submitAttempt(
        WithdrawalPayoutAttempt $attempt,
        string $source,
    ): WithdrawalPayoutAttempt {
        try {
            $response = $this->gateway->createPayoutOrder($attempt);
        } catch (Qc168UncertainSubmissionException $exception) {
            $this->recordSubmissionFailure($attempt, $exception, false, $source);

            throw $exception;
        } catch (Throwable $exception) {
            $this->recordSubmissionFailure($attempt, $exception, true, $source);

            throw $exception;
        }

        $payment = data_get($response, 'data.payment', []);
        $payment = is_array($payment) ? $payment : [];
        $providerStatus = strtolower((string) ($payment['payment_status'] ?? WithdrawalPayoutAttempt::STATUS_PENDING));
        $providerStatus = in_array($providerStatus, self::PROVIDER_STATUSES, true)
            ? $providerStatus
            : WithdrawalPayoutAttempt::STATUS_PENDING;
        $storedStatus = $providerStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED
            ? WithdrawalPayoutAttempt::STATUS_PENDING
            : $providerStatus;

        $submittedAttempt = DB::transaction(function () use ($attempt, $response, $source, $payment, $storedStatus): WithdrawalPayoutAttempt {
            $lockedAttempt = WithdrawalPayoutAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();
            $withdrawRequest = WithdrawRequest::query()
                ->whereKey($lockedAttempt->withdraw_request_id)
                ->lockForUpdate()
                ->firstOrFail();
            $submittedAt = now();

            $lockedAttempt->forceFill([
                'gateway_status' => $storedStatus,
                'gateway_order_uid' => $this->nullableString($payment['order_uid'] ?? null),
                'gateway_payment_uid' => $this->nullableString($payment['payment_uid'] ?? null),
                'gateway_final_amount' => array_key_exists('final_amount', $payment)
                    ? $this->normalizeAmount($payment['final_amount'])
                    : null,
                'submitted_at' => $submittedAt,
                'failure_reason' => $this->nullableString($payment['payment_fail_reason'] ?? null),
                'provider_response' => $response,
                'provider_meta' => array_merge($lockedAttempt->provider_meta ?? [], [
                    'submission_result' => 'success',
                    'order_created' => true,
                    'submission_received_at' => $submittedAt->toAtomString(),
                ]),
            ])->save();

            $withdrawRequest->forceFill([
                'payout_status' => $this->withdrawalStatus($storedStatus),
                'gateway_submitted_at' => $withdrawRequest->gateway_submitted_at ?: $submittedAt,
                'gateway_payment_status' => $storedStatus,
                'gateway_final_amount' => $lockedAttempt->gateway_final_amount,
                'gateway_fail_reason' => $lockedAttempt->failure_reason,
            ])->save();

            $this->eventRecorder->recordWithdrawal(
                'withdrawal.gateway_submitted',
                $withdrawRequest,
                $lockedAttempt,
                source: $source,
                paymentStatus: $storedStatus,
                payload: [
                    'attempt_no' => $lockedAttempt->attempt_no,
                    'submitted_at' => $submittedAt->toAtomString(),
                ],
            );

            return $lockedAttempt;
        });

        if ($providerStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED) {
            $this->processProviderState($submittedAttempt, $payment, $source.'_create_response');
        }

        return $submittedAttempt->fresh();
    }

    private function recordSubmissionFailure(
        WithdrawalPayoutAttempt $attempt,
        Throwable $exception,
        bool $definitive,
        string $source,
    ): void {
        DB::transaction(function () use ($attempt, $exception, $definitive, $source): void {
            $lockedAttempt = WithdrawalPayoutAttempt::query()
                ->whereKey($attempt->id)
                ->lockForUpdate()
                ->firstOrFail();
            $withdrawRequest = WithdrawRequest::query()
                ->whereKey($lockedAttempt->withdraw_request_id)
                ->lockForUpdate()
                ->firstOrFail();
            $message = mb_substr($exception->getMessage(), 0, 1000);
            $providerRejected = $exception instanceof Qc168OrderRejectedException;
            $status = $definitive
                ? WithdrawalPayoutAttempt::STATUS_CREATE_FAILED
                : WithdrawalPayoutAttempt::STATUS_CREATE_PENDING;

            $lockedAttempt->forceFill([
                'gateway_status' => $status,
                'failed_at' => $definitive ? now() : null,
                'failure_reason' => $message,
                'provider_meta' => array_merge($lockedAttempt->provider_meta ?? [], [
                    'submission_result' => $definitive ? 'failed' : 'uncertain',
                    'submission_definitive_failure' => $definitive,
                    'provider_result_error' => $providerRejected,
                    'order_created' => $definitive ? false : null,
                    'submission_failed_at' => now()->toAtomString(),
                ]),
            ])->save();

            $withdrawRequest->forceFill([
                'payout_status' => $definitive
                    ? WithdrawRequest::PAYOUT_CREATE_FAILED
                    : WithdrawRequest::PAYOUT_SUBMITTED,
                'gateway_payment_status' => $status,
                'gateway_fail_reason' => $message,
            ])->save();

            $this->eventRecorder->recordWithdrawal(
                $definitive ? 'withdrawal.gateway_create_failed' : 'withdrawal.gateway_submission_uncertain',
                $withdrawRequest,
                $lockedAttempt,
                source: $source,
                reason: $message,
                paymentStatus: $status,
                payload: [
                    'submission_definitive_failure' => $definitive,
                    'provider_result_error' => $providerRejected,
                    'order_created' => $definitive ? false : null,
                ],
                processingResult: $definitive ? 'create_failed' : 'submission_uncertain',
            );
        });
    }

    private function validateProviderState(WithdrawalPayoutAttempt $attempt, array $payload): ?string
    {
        if ($attempt->provider !== WithdrawRequest::GATEWAY_QC168) {
            return 'provider_mismatch';
        }

        if (! isset($payload['merchant_order_id'])
            || ! hash_equals($attempt->merchant_order_id, (string) $payload['merchant_order_id'])) {
            return 'merchant_order_mismatch';
        }

        if (! isset($payload['merchant_uid'])
            || ! hash_equals((string) config('services.qc168.merchant_uid'), (string) $payload['merchant_uid'])) {
            return 'merchant_mismatch';
        }

        if (($payload['transaction_type'] ?? null) !== 'expense') {
            return 'transaction_type_mismatch';
        }

        if (isset($payload['accounting_type']) && $payload['accounting_type'] !== 'pay') {
            return 'accounting_type_mismatch';
        }

        if (isset($payload['payment'])
            && ! hash_equals($attempt->payment_channel, (string) $payload['payment'])) {
            return 'payment_method_mismatch';
        }

        if (! isset($payload['currency'])
            || strtolower((string) $payload['currency']) !== strtolower($attempt->currency)) {
            return 'currency_mismatch';
        }

        $localAmount = $this->normalizeAmount($attempt->amount);
        $providerAmount = $this->normalizeAmount($payload['amount'] ?? null);

        if ($localAmount === null || $providerAmount === null || ! hash_equals($localAmount, $providerAmount)) {
            return 'amount_mismatch';
        }

        if (array_key_exists('final_amount', $payload)
            && $this->normalizeAmount($payload['final_amount']) === null) {
            return 'invalid_final_amount';
        }

        $paymentStatus = strtolower((string) ($payload['payment_status'] ?? ''));

        return in_array($paymentStatus, self::PROVIDER_STATUSES, true)
            ? null
            : 'unknown_payment_status';
    }

    private function shouldAcceptStatus(?string $currentStatus, string $incomingStatus): bool
    {
        if (! $currentStatus
            || in_array($currentStatus, [
                WithdrawalPayoutAttempt::STATUS_CREATE_PENDING,
                WithdrawalPayoutAttempt::STATUS_CREATE_FAILED,
            ], true)
            || $currentStatus === $incomingStatus) {
            return true;
        }

        if ($currentStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED) {
            return false;
        }

        if ($incomingStatus === WithdrawalPayoutAttempt::STATUS_COMPLETED) {
            return true;
        }

        if (in_array($currentStatus, WithdrawalPayoutAttempt::PROVIDER_FINAL_STATUSES, true)) {
            return false;
        }

        $precedence = [
            WithdrawalPayoutAttempt::STATUS_NONE => 0,
            WithdrawalPayoutAttempt::STATUS_PENDING => 1,
            WithdrawalPayoutAttempt::STATUS_PREPARING => 2,
            WithdrawalPayoutAttempt::STATUS_PROCESSING => 3,
            WithdrawalPayoutAttempt::STATUS_FAILED => 4,
            WithdrawalPayoutAttempt::STATUS_REJECT => 4,
            WithdrawalPayoutAttempt::STATUS_REFUND => 4,
            WithdrawalPayoutAttempt::STATUS_OVERDUE => 4,
        ];

        return ($precedence[$incomingStatus] ?? -1) >= ($precedence[$currentStatus] ?? -1);
    }

    private function withdrawalStatus(string $providerStatus): string
    {
        return match ($providerStatus) {
            WithdrawalPayoutAttempt::STATUS_PREPARING => WithdrawRequest::PAYOUT_PREPARING,
            WithdrawalPayoutAttempt::STATUS_PROCESSING => WithdrawRequest::PAYOUT_PROCESSING,
            WithdrawalPayoutAttempt::STATUS_COMPLETED => WithdrawRequest::PAYOUT_COMPLETED,
            WithdrawalPayoutAttempt::STATUS_FAILED => WithdrawRequest::PAYOUT_FAILED,
            WithdrawalPayoutAttempt::STATUS_REJECT => WithdrawRequest::PAYOUT_REJECTED,
            WithdrawalPayoutAttempt::STATUS_OVERDUE => WithdrawRequest::PAYOUT_OVERDUE,
            WithdrawalPayoutAttempt::STATUS_REFUND => WithdrawRequest::PAYOUT_REFUND_REVIEW,
            default => WithdrawRequest::PAYOUT_PENDING,
        };
    }

    private function isCurrentAttempt(
        WithdrawRequest $withdrawRequest,
        WithdrawalPayoutAttempt $attempt,
    ): bool {
        return hash_equals(
            (string) $withdrawRequest->gateway_current_merchant_order_id,
            $attempt->merchant_order_id,
        );
    }

    private function normalizeAmount(mixed $amount): ?string
    {
        if (! is_string($amount) && ! is_int($amount) && ! is_float($amount)) {
            return null;
        }

        $value = trim((string) $amount);

        if (! preg_match('/^\+?(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            return null;
        }

        $whole = ltrim($matches[1], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[2] ?? '';

        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            return null;
        }

        return $whole.'.'.str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== ''
            ? (string) $value
            : null;
    }
}
