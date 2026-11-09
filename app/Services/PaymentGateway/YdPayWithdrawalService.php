<?php

namespace App\Services\PaymentGateway;

use App\Models\AdminUser;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Services\Settings\YdPaySettings;
use App\Services\WithdrawService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class YdPayWithdrawalService
{
    public function __construct(
        private readonly YdPayGateway $gateway,
        private readonly PaymentGatewayEventRecorder $events,
        private readonly WithdrawService $withdrawals,
    ) {}

    public function approve(WithdrawRequest $request, AdminUser $admin, ?string $reason = null): void
    {
        Gate::forUser($admin)->authorize('approveScheduledGateway', $request);
        DB::transaction(function () use ($request, $admin, $reason): void {
            $request = WithdrawRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $request->isPending() || ! $this->gateway->isAvailable('withdraw')) {
                throw new RuntimeException('YDPAY withdrawal unavailable or request already reviewed.');
            }
            $this->bankCode($request->bank_code);
            $this->gateway->publicUrl('payment-gateway.ydpay.withdraw.callback');
            Validator::make($request->only(['bank_name', 'bank_account_name', 'bank_account_no']), [
                'bank_name' => ['required', 'string', 'max:128'],
                'bank_account_name' => ['required', 'string', 'max:128'],
                'bank_account_no' => ['required', 'string', 'max:64'],
            ])->validate();
            $hours = app(YdPaySettings::class)->releaseHours();
            $approvedAt = now();
            $request->forceFill([
                'status' => 'approved', 'approval_mode' => WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED,
                'gateway_provider' => 'ydpay', 'payout_status' => WithdrawRequest::PAYOUT_WAITING_RELEASE,
                'reviewed_by' => $admin->id, 'reviewed_at' => $approvedAt, 'admin_remark' => $reason,
                'gateway_release_at' => $approvedAt->copy()->addHours($hours), 'gateway_release_delay_hours_snapshot' => $hours,
            ])->save();
            $this->events->recordWithdrawal('withdrawal.gateway_waiting_release', $request, actor: $admin,
                source: 'filament', reason: $reason, payload: ['delay_hours' => $hours]);
        });
    }

    public function dispatch(WithdrawRequest $request, bool $retry = false, ?AdminUser $actor = null, ?string $reason = null): ?WithdrawalPayoutAttempt
    {
        if (! $this->gateway->isAvailable('withdraw')) {
            return null;
        }
        $attempt = DB::transaction(function () use ($request, $retry, $actor, $reason): ?WithdrawalPayoutAttempt {
            $request = WithdrawRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($request->gateway_provider !== 'ydpay' || ! $request->isApproved()
                || $request->hasGatewayCancellationLedger() || $request->isPayoutCompleted()) {
                return null;
            }
            $latest = $request->payoutAttempts()->latest('attempt_no')->first();
            if ($retry) {
                if ($request->payout_status !== 'failed' || $latest?->gateway_status !== 'failed') {
                    throw new RuntimeException('YDPAY retry requires a verified final failure.');
                }
            } elseif ($request->payout_status !== 'waiting_release' || ! $request->gateway_release_at
                || $request->gateway_release_at->isFuture() || $latest) {
                return null;
            }
            $bankCode = $this->bankCode($request->bank_code);
            $attempt = $request->payoutAttempts()->create([
                'attempt_no' => ($latest?->attempt_no ?? 0) + 1, 'provider' => 'ydpay',
                'merchant_order_id' => now()->format('YmdHis').random_int(100000000000, 999999999999),
                'amount' => $request->net_amount, 'currency' => 'vnd',
                'payment_channel' => (string) config('services.ydpay.withdraw_gateway_id'),
                'bank_name_snapshot' => $request->bank_name, 'bank_code_snapshot' => $bankCode,
                'bank_account_name_snapshot' => $request->bank_account_name,
                'bank_account_no_snapshot' => $request->bank_account_no, 'gateway_status' => 'create_pending',
            ]);
            $request->forceFill(['payout_status' => 'submitted', 'gateway_payment_status' => 'create_pending',
                'gateway_current_merchant_order_id' => $attempt->merchant_order_id])->save();
            $this->events->recordWithdrawal($retry ? 'withdrawal.gateway_retry' : 'withdrawal.gateway_submitted',
                $request, $attempt, actor: $actor, source: 'ydpay_dispatch', reason: $reason);

            return $attempt;
        });
        if (! $attempt) {
            return null;
        }
        try {
            $response = $this->gateway->createPayout([
                'gatewayId' => $attempt->payment_channel, 'mchOrderNo' => $attempt->merchant_order_id,
                'amount' => $this->gateway->minorAmount($attempt->amount),
                'bankCode' => $attempt->bank_code_snapshot, 'bankName' => $attempt->bank_name_snapshot,
                'accountName' => $attempt->bank_account_name_snapshot, 'accountNo' => $attempt->bank_account_no_snapshot,
                'accountAttr' => '0', 'notifyUrl' => $this->gateway->publicUrl('payment-gateway.ydpay.withdraw.callback'),
            ]);
            // Creation responses omit amount/order identity. Query the original order for finalization evidence.
            if (! is_scalar($response['agentpayOrderId'] ?? null) || (string) $response['agentpayOrderId'] === ''
                || (string) ($response['gatewayId'] ?? '') !== $attempt->payment_channel) {
                throw new RuntimeException('YDPAY create response order or gateway mismatch.');
            }
            DB::transaction(function () use ($request, $attempt, $response): void {
                $lockedRequest = WithdrawRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                $lockedAttempt = WithdrawalPayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($lockedAttempt->gateway_order_uid && $lockedAttempt->gateway_order_uid !== (string) $response['agentpayOrderId']) {
                    throw new RuntimeException('YDPAY platform order mismatch.');
                }
                $lockedAttempt->forceFill(['gateway_order_uid' => (string) $response['agentpayOrderId'], 'submitted_at' => now()])->save();
                $this->events->recordWithdrawal('ydpay.payout.create_response', $lockedRequest, $lockedAttempt,
                    source: 'ydpay_dispatch', payload: $response);
            });
            $this->sync($request);
        } catch (Throwable $exception) {
            $this->events->recordWithdrawal('ydpay.payout.submission_uncertain', $request, $attempt,
                source: 'ydpay_dispatch', processingResult: 'query_required');
            throw $exception;
        }

        return $attempt->fresh();
    }

    public function sync(WithdrawRequest $request, string $source = 'sync', ?AdminUser $actor = null, ?string $reason = null, ?string $ip = null): array
    {
        $attempt = $request->payoutAttempts()->where('provider', 'ydpay')->latest('attempt_no')->firstOrFail();
        $result = $this->process($this->gateway->query($attempt->merchant_order_id, 'withdraw'), 'sync');
        $this->events->recordWithdrawal('withdrawal.gateway_sync', $request->fresh(), $attempt->fresh(),
            actor: $actor, source: $source, reason: $reason, payload: array_filter(['ip' => $ip]), processingResult: $result);

        return ['accepted' => true, 'result' => $result];
    }

    public function retry(WithdrawRequest $request, AdminUser $admin, string $reason, ?string $ip = null): ?WithdrawalPayoutAttempt
    {
        Gate::forUser($admin)->authorize('retryGateway', $request);
        $this->sync($request, 'filament_retry_check', $admin, $reason, $ip);

        return $this->dispatch($request->fresh(), true, $admin, $reason);
    }

    public function cancel(WithdrawRequest $request, AdminUser $admin, string $reason, ?string $ip = null): WithdrawRequest
    {
        Gate::forUser($admin)->authorize('cancelGateway', $request);
        $this->sync($request, 'filament_cancel_check', $admin, $reason, $ip);

        return $this->withdrawals->cancelPayout($request->fresh(), $request->payoutAttempts()->latest('attempt_no')->firstOrFail(), $admin, $reason, $ip);
    }

    public function process(array $payload, string $source): string
    {
        if (! $this->gateway->verify($payload) || isset($payload['sandbox'])) {
            throw new RuntimeException('Invalid YDPAY payout signature or environment.');
        }
        $attempt = WithdrawalPayoutAttempt::query()->where('provider', 'ydpay')
            ->where('merchant_order_id', $payload['mchOrderNo'] ?? '')->firstOrFail();

        return DB::transaction(function () use ($attempt, $payload, $source): string {
            $request = WithdrawRequest::query()->whereKey($attempt->withdraw_request_id)->lockForUpdate()->firstOrFail();
            $attempt = WithdrawalPayoutAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $status = (string) ($payload['status'] ?? '');
            if ($request->gateway_provider !== 'ydpay' || $attempt->currency !== 'vnd'
                || (string) ($payload['amount'] ?? '') !== $this->gateway->minorAmount($attempt->amount)
                || ! is_scalar($payload['agentpayOrderId'] ?? null) || (string) $payload['agentpayOrderId'] === ''
                || ($attempt->gateway_order_uid && $attempt->gateway_order_uid !== (string) $payload['agentpayOrderId'])
                || (string) ($payload['gatewayId'] ?? '') !== $attempt->payment_channel
                || ! in_array($status, $source === 'callback' ? ['2', '3'] : ['0', '1', '2', '3', '4', '5', '6'], true)) {
                throw new RuntimeException('YDPAY payout evidence does not match the original attempt.');
            }
            $state = match ($status) {
                '2' => 'completed', '3' => 'failed', '6' => 'refund_review', default => 'processing'
            };
            $cancelled = $request->payout_status === 'cancelled' || $request->hasGatewayCancellationLedger();
            $current = $request->gateway_current_merchant_order_id === $attempt->merchant_order_id;
            $conflict = $state === 'completed' && ($cancelled || ! $current || $request->isManuallySettledPayout());
            if (! $attempt->isCompleted()) {
                $attempt->forceFill(['gateway_order_uid' => (string) $payload['agentpayOrderId'],
                    'gateway_status' => $state === 'refund_review' ? 'refund' : $state,
                    'completed_at' => $state === 'completed' ? now() : null,
                    'failure_reason' => is_string($payload['transMsg'] ?? null) ? $payload['transMsg'] : null,
                    'provider_response' => $payload])->save();
            }
            if ($conflict || ($state === 'refund_review' && ! $cancelled)) {
                $request->forceFill(['payout_status' => 'refund_review', 'gateway_payment_status' => $state,
                    'gateway_fail_reason' => $cancelled
                        ? str_replace('QC168', 'YDPAY', __('withdraw.gateway_completed_after_cancel_review'))
                        : 'YDPAY financial review required.',
                    'gateway_meta' => array_merge($request->gateway_meta ?? [], [
                        'financial_review_reason' => $cancelled ? 'provider_completed_after_cancel' : 'ydpay_provider_conflict',
                        'financial_review_detected_at' => now()->toAtomString(),
                        'financial_review_attempt_id' => $attempt->id,
                        'financial_review_merchant_order_id' => $attempt->merchant_order_id,
                    ])])->save();
            } elseif (! $cancelled && $current && ! $request->isPayoutCompleted() && $request->payout_status !== 'refund_review') {
                if ($state === 'completed') {
                    $this->withdrawals->finalizePayout($request, $attempt, 'ydpay_'.$source);
                } else {
                    $request->forceFill(['payout_status' => $state, 'gateway_payment_status' => $state,
                        'gateway_fail_reason' => $attempt->failure_reason])->save();
                }
            }
            $this->events->recordWithdrawal('ydpay.payout.'.($conflict ? 'financial_review' : $state), $request, $attempt,
                source: 'ydpay_'.$source, paymentStatus: $state, payload: $payload, signatureValid: true);

            return $conflict ? 'financial_review' : $state;
        });
    }

    private function bankCode(?string $bin): string
    {
        // These are YDPAY's gateway 22/23 codes, not QC168/NAPAS BINs.
        $codes = ['970425' => '1', '970416' => '2', '970405' => '3', '970409' => '4', '970418' => '6',
            '970431' => '10', '970408' => '11', '970437' => '12', '458761' => '14', '970434' => '15',
            '970452' => '17', '970449' => '18', '970422' => '19', '970426' => '21', '970428' => '22',
            '970419' => '23', '970448' => '24', '970430' => '25', '970412' => '27', '970403' => '28',
            '970400' => '29', '970429' => '30', '970440' => '31', '970443' => '32', '970424' => '33',
            '970407' => '35', '970423' => '37', '970427' => '41', '970454' => '42', '970436' => '44',
            '970415' => '45', '970441' => '46', '970432' => '48'];
        if (! in_array((string) config('services.ydpay.withdraw_gateway_id'), ['22', '23'], true) || ! isset($codes[$bin])) {
            throw new RuntimeException('YDPAY requires a supported bank and confirmed VND gateway 22/23.');
        }

        return $codes[$bin];
    }
}
