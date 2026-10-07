<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WithdrawService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly Qc168Settings $qc168Settings,
        private readonly Qc168Gateway $qc168Gateway,
        private readonly PaymentGatewayEventRecorder $eventRecorder,
    ) {}

    public function submit(
        User $user,
        float|string $amount,
    ): WithdrawRequest {
        if (! $user->canWithdraw()) {
            throw new RuntimeException(__('withdraw.withdraw_disabled'));
        }

        if ($user->status !== 'active') {
            throw new RuntimeException(__('withdraw.account_not_active'));
        }

        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException(__('withdraw.invalid_amount'));
        }

        return DB::transaction(function () use ($user, $amount): WithdrawRequest {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $bankAccount = $lockedUser->bankAccounts()
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $bankAccount) {
                if ($lockedUser->bankAccountReviewRequests()->where('status', 'pending')->exists()) {
                    throw new RuntimeException(__('bank_account.under_review'));
                }

                throw new RuntimeException(__('bank_account.bind_required_first'));
            }

            if (! $bankAccount->isActive()) {
                throw new RuntimeException(__('bank_account.unavailable'));
            }

            $vipLevel = $lockedUser->currentVipLevel();

            $vipLevelNumber = $vipLevel ? (int) $vipLevel->level : 1;

            $minWithdrawAmount = $vipLevel
                ? (float) $vipLevel->min_withdraw_amount
                : 0;

            if ($amount < $minWithdrawAmount) {
                throw new RuntimeException(__('withdraw.below_minimum_amount', [
                    'amount' => number_format($minWithdrawAmount, 2),
                ]));
            }

            $feePercent = $this->withdrawFeePercent($vipLevel);
            $feeAmount = $this->calculateFeeAmount($feePercent, $amount);
            $netAmount = round($amount - $feeAmount, 2);

            if ($netAmount <= 0) {
                throw new RuntimeException(__('withdraw.invalid_net_amount'));
            }

            $withdrawRequest = WithdrawRequest::query()->create([
                'user_id' => $lockedUser->id,
                'user_bank_account_id' => $bankAccount->id,

                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,

                'vip_level_snapshot' => $vipLevelNumber,
                'min_withdraw_amount_snapshot' => $minWithdrawAmount,
                'withdraw_fee_percent_snapshot' => $feePercent,

                // snapshot bank info, so future admin changes do not modify old request history
                'bank_name' => $bankAccount->bank_name,
                'bank_code' => $bankAccount->bank_code,
                'bank_account_name' => $bankAccount->bank_account_name,
                'bank_account_no' => $bankAccount->bank_account_no,

                'status' => 'pending',
            ]);

            $this->walletService->lock(
                user: $lockedUser,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'withdraw_request_locked',
                amount: $amount,
                reference: $withdrawRequest,
                remarks: 'Withdraw request submitted',
                meta: [
                    'withdraw_request_id' => $withdrawRequest->id,
                    'fee_percent' => $feePercent,
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                    'vip_level_snapshot' => $vipLevelNumber,
                    'min_withdraw_amount_snapshot' => $minWithdrawAmount,
                    'user_bank_account_id' => $bankAccount->id,
                ],
            );

            return $withdrawRequest;
        });
    }

    public function approveManually(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        ?string $adminRemark = null,
        string $source = 'filament',
        ?string $ip = null,
    ): WithdrawRequest {
        Gate::forUser($adminUser)->authorize('approveManual', $withdrawRequest);

        return DB::transaction(function () use ($withdrawRequest, $adminUser, $adminRemark, $source, $ip): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($lockedRequest);

            $approvedAt = now();
            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletService->consumeLocked(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                amount: $lockedRequest->amount,
            );

            $lockedRequest->forceFill([
                'status' => WithdrawRequest::STATUS_APPROVED,
                'approval_mode' => WithdrawRequest::APPROVAL_MODE_MANUAL,
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => $approvedAt,
                'admin_remark' => $adminRemark,
                'gateway_provider' => null,
                'payout_status' => WithdrawRequest::PAYOUT_COMPLETED,
                'gateway_release_at' => null,
                'gateway_release_delay_hours_snapshot' => null,
                'gateway_submitted_at' => null,
                'gateway_completed_at' => null,
                'gateway_current_merchant_order_id' => null,
                'gateway_payment_status' => null,
                'gateway_final_amount' => null,
                'gateway_fail_reason' => null,
                'gateway_meta' => null,
            ])->save();

            $this->eventRecorder->recordWithdrawal(
                'withdrawal.approved_manual',
                $lockedRequest,
                actor: $adminUser,
                source: $source,
                reason: $adminRemark,
                paymentStatus: WithdrawRequest::PAYOUT_COMPLETED,
                payload: array_filter([
                    'ip' => $ip,
                    'approval_mode' => WithdrawRequest::APPROVAL_MODE_MANUAL,
                    'approved_at' => $approvedAt->toAtomString(),
                    'reserved_amount_consumed' => (string) $lockedRequest->amount,
                ], fn ($value): bool => $value !== null),
            );

            return $lockedRequest;
        });
    }

    public function markFailedPayoutPaid(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        string $merchantOrderId,
        string $paymentReference,
        string $reason,
        bool $confirmedPaid,
        ?string $ip = null,
    ): WithdrawRequest {
        $withdrawRequest->refresh();
        Gate::forUser($adminUser)->authorize('approveManual', $withdrawRequest);
        $paymentReference = trim($paymentReference);
        $reason = trim($reason);
        Validator::make([
            'payment_reference' => $paymentReference,
            'reason' => $reason,
            'confirmed_paid' => $confirmedPaid,
        ], [
            'payment_reference' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
            'confirmed_paid' => ['accepted'],
        ])->validate();

        $attempt = $withdrawRequest->payoutAttempts()->latest('attempt_no')->first();
        if (! $withdrawRequest->canMarkPayoutPaid()
            || ! $attempt?->isSafeFinalFailure()
            || $attempt->merchant_order_id !== $merchantOrderId) {
            throw new RuntimeException(__('withdraw.mark_paid_not_allowed'));
        }

        $definitiveCreateFailure = $attempt->gateway_status === WithdrawalPayoutAttempt::STATUS_CREATE_FAILED
            && data_get($attempt->provider_meta, 'submission_definitive_failure') === true;
        if (! $definitiveCreateFailure && ! $attempt->isManuallyConfirmedUnpaid()) {
            $result = app(Qc168WithdrawalPayoutService::class)->syncAttempt(
                $attempt, 'filament_manual_paid_check', $adminUser, $reason, $ip,
            );
            if (! $result['accepted'] || ! in_array($result['payment_status'], [
                WithdrawalPayoutAttempt::STATUS_FAILED,
                WithdrawalPayoutAttempt::STATUS_REJECT,
                WithdrawalPayoutAttempt::STATUS_OVERDUE,
            ], true)) {
                throw new RuntimeException(__('withdraw.mark_paid_not_allowed'));
            }
        }

        return DB::transaction(function () use ($withdrawRequest, $adminUser, $attempt, $merchantOrderId, $paymentReference, $reason, $ip): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()->whereKey($withdrawRequest->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($adminUser)->authorize('approveManual', $lockedRequest);
            $attempts = $lockedRequest->payoutAttempts()->orderByDesc('attempt_no')->lockForUpdate()->get();
            $latest = $attempts->first();
            if (! $lockedRequest->canMarkPayoutPaid()
                || $latest?->id !== $attempt->id
                || $lockedRequest->gateway_current_merchant_order_id !== $merchantOrderId
                || $attempts->contains(fn (WithdrawalPayoutAttempt $item): bool => ! $item->isSafeFinalFailure())) {
                throw new RuntimeException(__('withdraw.mark_paid_not_allowed'));
            }

            $user = $lockedRequest->user()->lockForUpdate()->firstOrFail();
            $this->walletService->consumeLocked($user, Wallet::TYPE_WITHDRAWABLE, $lockedRequest->amount);
            $manualPayout = [
                'completed_at' => now()->toAtomString(),
                'admin_user_id' => $adminUser->id,
                'payment_reference' => $paymentReference,
                'reason' => $reason,
                'attempt_id' => $latest->id,
                'merchant_order_id' => $merchantOrderId,
                'reserved_amount_consumed' => (string) $lockedRequest->amount,
                'user_payout_amount' => (string) $lockedRequest->net_amount,
            ];
            // Preserve the original approval and provider failure history.
            $lockedRequest->forceFill([
                'payout_status' => WithdrawRequest::PAYOUT_COMPLETED,
                'gateway_meta' => array_merge($lockedRequest->gateway_meta ?? [], ['manual_payout' => $manualPayout]),
            ])->save();
            $this->eventRecorder->recordWithdrawal(
                'withdrawal.failed_payout_manually_completed',
                $lockedRequest,
                $latest,
                $adminUser,
                'filament',
                $reason,
                WithdrawRequest::PAYOUT_COMPLETED,
                array_merge($manualPayout, ['ip' => $ip]),
            );

            return $lockedRequest;
        });
    }

    public function revertManualApproval(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        int $approvalEventId,
        string $reason,
        bool $confirmedUnpaid,
        ?string $ip = null,
    ): WithdrawRequest {
        return DB::transaction(function () use ($withdrawRequest, $adminUser, $approvalEventId, $reason, $confirmedUnpaid, $ip): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()->whereKey($withdrawRequest->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($adminUser)->authorize('revertManualApproval', $lockedRequest);
            $reason = trim($reason);
            Validator::make([
                'reason' => $reason,
                'confirmed_unpaid' => $confirmedUnpaid,
            ], [
                'reason' => ['required', 'string', 'max:1000'],
                'confirmed_unpaid' => ['accepted'],
            ])->validate();

            $approval = $lockedRequest->manualApprovalEvents()->latest('id')->first();
            if (! $lockedRequest->canRevertManualApproval()
                || ! $approval
                || $approval->id !== $approvalEventId
                || (string) data_get($approval->payload, 'reserved_amount_consumed') !== (string) $lockedRequest->amount
                || $lockedRequest->payoutAttempts()->exists()
                || $lockedRequest->hasGatewayCancellationLedger()) {
                throw new RuntimeException(__('withdraw.revert_manual_not_allowed'));
            }

            foreach ([
                'gateway_release_at', 'gateway_release_delay_hours_snapshot',
                'gateway_submitted_at', 'gateway_completed_at', 'gateway_last_callback_at',
                'gateway_last_synced_at', 'gateway_current_merchant_order_id',
                'gateway_payment_status', 'gateway_final_amount', 'gateway_fail_reason', 'gateway_meta',
            ] as $field) {
                if ($lockedRequest->getAttribute($field) !== null) {
                    throw new RuntimeException(__('withdraw.revert_manual_not_allowed'));
                }
            }

            $user = $lockedRequest->user()->lockForUpdate()->firstOrFail();
            $wallet = Wallet::query()->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_WITHDRAWABLE)->lockForUpdate()->firstOrFail();
            $lockedBefore = $wallet->locked_balance;

            // Undo consumeLocked without crediting spendable funds or debiting the user again.
            $wallet->increment('locked_balance', $lockedRequest->amount);
            $wallet->refresh();

            $reset = [
                'status' => WithdrawRequest::STATUS_PENDING,
                'approval_mode' => null,
                'payout_status' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'admin_remark' => null,
            ];
            $before = array_intersect_key($lockedRequest->getRawOriginal(), $reset);

            // This guarded reset is the only exception here to approval-mode immutability.
            $updated = WithdrawRequest::query()->whereKey($lockedRequest->id)
                ->where('status', WithdrawRequest::STATUS_APPROVED)
                ->where('approval_mode', WithdrawRequest::APPROVAL_MODE_MANUAL)
                ->where('payout_status', WithdrawRequest::PAYOUT_COMPLETED)
                ->update($reset);
            if ($updated !== 1) {
                throw new RuntimeException(__('withdraw.revert_manual_not_allowed'));
            }
            $lockedRequest->refresh();
            $this->eventRecorder->recordWithdrawal(
                'withdrawal.manual_approval_reverted',
                $lockedRequest,
                actor: $adminUser,
                source: 'filament',
                reason: $reason,
                paymentStatus: WithdrawRequest::STATUS_PENDING,
                payload: [
                    'ip' => $ip,
                    'approval_event_id' => $approvalEventId,
                    'confirmed_unpaid' => true,
                    'before' => $before,
                    'after' => $reset,
                    'wallet_id' => $wallet->id,
                    'available_balance' => $wallet->balance,
                    'locked_balance_before' => $lockedBefore,
                    'locked_balance_after' => $wallet->locked_balance,
                    'reserved_amount_restored' => $lockedRequest->amount,
                ],
            );

            return $lockedRequest;
        }, 3);
    }

    public function approveWithScheduledGateway(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        ?string $adminRemark = null,
        string $source = 'filament',
        ?string $ip = null,
    ): WithdrawRequest {
        Gate::forUser($adminUser)->authorize('approveScheduledGateway', $withdrawRequest);

        return $this->approveForGateway(
            $withdrawRequest,
            $adminUser,
            WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED,
            $this->qc168Settings->withdrawReleaseHours(),
            'withdrawal.approved_gateway_scheduled',
            $adminRemark,
            $source,
            $ip,
        );
    }

    public function approveWithInstantGateway(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        ?string $adminRemark = null,
        string $source = 'filament',
        ?string $ip = null,
    ): WithdrawRequest {
        Gate::forUser($adminUser)->authorize('approveInstantGateway', $withdrawRequest);

        $approvedRequest = $this->approveForGateway(
            $withdrawRequest,
            $adminUser,
            WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT,
            0,
            'withdrawal.approved_gateway_instant',
            $adminRemark,
            $source,
            $ip,
        );

        try {
            $attempt = app(Qc168WithdrawalPayoutService::class)->dispatch(
                $approvedRequest,
                'filament_instant',
            );

            if (! $attempt) {
                throw new RuntimeException(__('withdraw.instant_submission_not_created'));
            }

            $this->recordInstantAttempt($approvedRequest, $attempt, $adminUser, $source, $ip);
        } catch (Throwable $exception) {
            $attempt = $approvedRequest->payoutAttempts()->latest('attempt_no')->first();

            if ($attempt) {
                $this->recordInstantAttempt(
                    $approvedRequest->fresh(),
                    $attempt,
                    $adminUser,
                    $source,
                    $ip,
                    $exception->getMessage(),
                );
            }

            throw $exception;
        }

        return $approvedRequest->fresh();
    }

    public function reject(
        WithdrawRequest $withdrawRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
        string $source = 'filament',
        ?string $ip = null,
    ): WithdrawRequest {
        return DB::transaction(function () use ($withdrawRequest, $adminUser, $adminRemark, $source, $ip): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException('Only pending withdraw requests can be rejected.');
            }

            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRequest->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $adminUser?->id,
                'reviewed_at' => now(),
                'admin_remark' => $adminRemark,
            ])->save();

            $this->walletService->releaseLocked(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'withdraw_request_rejected',
                amount: $lockedRequest->amount,
                reference: $lockedRequest,
                remarks: 'Withdraw request rejected',
                meta: [
                    'withdraw_request_id' => $lockedRequest->id,
                    'fee_percent' => $lockedRequest->withdraw_fee_percent_snapshot,
                    'fee_amount' => $lockedRequest->fee_amount,
                    'net_amount' => $lockedRequest->net_amount,
                    'admin_user_id' => $adminUser?->id,
                ],
            );

            $this->eventRecorder->recordWithdrawal(
                'withdrawal.rejected',
                $lockedRequest,
                actor: $adminUser,
                source: $source,
                reason: $adminRemark,
                payload: array_filter(['ip' => $ip], fn ($value): bool => $value !== null),
            );

            return $lockedRequest;
        });
    }

    public function finalizePayout(
        WithdrawRequest $withdrawRequest,
        WithdrawalPayoutAttempt $attempt,
        string $source,
    ): bool {
        return DB::transaction(function () use ($withdrawRequest, $attempt, $source): bool {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedRequest->isPayoutCompleted()) {
                return false;
            }

            if (! $lockedRequest->isApproved()) {
                throw new RuntimeException('Only approved withdrawals can be finalized.');
            }

            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletService->consumeLocked(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                amount: $lockedRequest->amount,
            );

            $completedAt = now();
            $lockedRequest->forceFill([
                'payout_status' => WithdrawRequest::PAYOUT_COMPLETED,
                'gateway_payment_status' => WithdrawalPayoutAttempt::STATUS_COMPLETED,
                'gateway_completed_at' => $lockedRequest->gateway_completed_at ?: $completedAt,
                'gateway_fail_reason' => $attempt->failure_reason,
            ])->save();

            $this->eventRecorder->recordWithdrawal(
                'withdrawal.gateway_completed',
                $lockedRequest,
                $attempt,
                source: $source,
                paymentStatus: WithdrawalPayoutAttempt::STATUS_COMPLETED,
                payload: [
                    'finalized_at' => $completedAt->toAtomString(),
                    'reserved_amount_consumed' => (string) $lockedRequest->amount,
                    'user_payout_amount' => (string) $attempt->amount,
                ],
            );

            return true;
        });
    }

    public function cancelPayout(
        WithdrawRequest $withdrawRequest,
        WithdrawalPayoutAttempt $attempt,
        AdminUser $adminUser,
        string $reason,
        ?string $ip = null,
    ): WithdrawRequest {
        return DB::transaction(function () use ($withdrawRequest, $attempt, $adminUser, $reason, $ip): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (WalletTransaction::query()
                ->where('type', 'withdraw_gateway_cancelled')
                ->where('reference_type', WithdrawRequest::class)
                ->where('reference_id', $lockedRequest->id)
                ->lockForUpdate()
                ->exists()) {
                throw new RuntimeException(__('withdraw.cancel_not_allowed'));
            }

            if (! $lockedRequest->isApproved() || $lockedRequest->isPayoutCompleted()) {
                throw new RuntimeException(__('withdraw.cancel_not_allowed'));
            }

            $lockedAttempt = WithdrawalPayoutAttempt::query()
                ->where('withdraw_request_id', $lockedRequest->id)
                ->latest('attempt_no')
                ->lockForUpdate()
                ->first();

            if (! in_array($lockedRequest->payout_status, [
                WithdrawRequest::PAYOUT_CREATE_FAILED,
                WithdrawRequest::PAYOUT_FAILED,
                WithdrawRequest::PAYOUT_REJECTED,
                WithdrawRequest::PAYOUT_OVERDUE,
            ], true) || ! $lockedAttempt?->isSafeFinalFailure() || $lockedAttempt->id !== $attempt->id) {
                throw new RuntimeException(__('withdraw.cancel_not_safe'));
            }

            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletService->releaseLocked(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'withdraw_gateway_cancelled',
                amount: $lockedRequest->amount,
                reference: $lockedRequest,
                remarks: 'Withdrawal payout cancelled and reserve released',
                meta: [
                    'withdraw_request_id' => $lockedRequest->id,
                    'withdrawal_payout_attempt_id' => $lockedAttempt->id,
                    'merchant_order_id' => $lockedAttempt->merchant_order_id,
                    'admin_user_id' => $adminUser->id,
                    'reason' => $reason,
                ],
                idempotencyKey: 'withdrawal_gateway_cancelled:'.$lockedRequest->id,
            );

            $lockedRequest->forceFill([
                'payout_status' => WithdrawRequest::PAYOUT_CANCELLED,
                'gateway_fail_reason' => $reason,
                'gateway_meta' => array_merge($lockedRequest->gateway_meta ?? [], [
                    'cancelled_at' => now()->toAtomString(),
                    'cancelled_by' => $adminUser->id,
                ]),
            ])->save();

            $this->eventRecorder->recordWithdrawal(
                'withdrawal.gateway_cancelled',
                $lockedRequest,
                $lockedAttempt,
                $adminUser,
                'filament',
                $reason,
                WithdrawRequest::PAYOUT_CANCELLED,
                array_filter(['ip' => $ip], fn ($value): bool => $value !== null),
            );

            return $lockedRequest;
        });
    }

    private function approveForGateway(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        string $approvalMode,
        int $delayHours,
        string $eventType,
        ?string $adminRemark,
        string $source,
        ?string $ip,
    ): WithdrawRequest {
        return DB::transaction(function () use (
            $withdrawRequest,
            $adminUser,
            $approvalMode,
            $delayHours,
            $eventType,
            $adminRemark,
            $source,
            $ip,
        ): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureGatewayAvailable();
            $this->ensurePending($lockedRequest);

            if (! preg_match('/^\d+$/', (string) $lockedRequest->bank_code)) {
                throw ValidationException::withMessages([
                    'bank_account' => __('withdraw.bank_not_supported'),
                ]);
            }

            $approvedAt = now();
            $releaseAt = $delayHours === 0
                ? $approvedAt->copy()
                : $approvedAt->copy()->addHours($delayHours);

            $lockedRequest->forceFill([
                'status' => WithdrawRequest::STATUS_APPROVED,
                'approval_mode' => $approvalMode,
                'reviewed_by' => $adminUser->id,
                'reviewed_at' => $approvedAt,
                'admin_remark' => $adminRemark,
                'gateway_provider' => WithdrawRequest::GATEWAY_QC168,
                'payout_status' => WithdrawRequest::PAYOUT_WAITING_RELEASE,
                'gateway_release_at' => $releaseAt,
                'gateway_release_delay_hours_snapshot' => $delayHours,
                'gateway_payment_status' => null,
                'gateway_fail_reason' => null,
            ])->save();

            $eventPayload = array_filter([
                'ip' => $ip,
                'approval_mode' => $approvalMode,
                'approved_at' => $approvedAt->toAtomString(),
                'delay_hours_snapshot' => $delayHours,
                'gateway_release_at' => $releaseAt->toAtomString(),
            ], fn ($value): bool => $value !== null);

            $this->eventRecorder->recordWithdrawal(
                $eventType,
                $lockedRequest,
                actor: $adminUser,
                source: $source,
                reason: $adminRemark,
                paymentStatus: WithdrawRequest::PAYOUT_WAITING_RELEASE,
                payload: $eventPayload,
            );
            $this->eventRecorder->recordWithdrawal(
                'withdrawal.gateway_waiting_release',
                $lockedRequest,
                actor: $adminUser,
                source: $source,
                paymentStatus: WithdrawRequest::PAYOUT_WAITING_RELEASE,
                payload: $eventPayload,
            );

            return $lockedRequest;
        });
    }

    private function recordInstantAttempt(
        WithdrawRequest $withdrawRequest,
        WithdrawalPayoutAttempt $attempt,
        AdminUser $adminUser,
        string $source,
        ?string $ip,
        ?string $failureReason = null,
    ): void {
        $this->eventRecorder->recordWithdrawal(
            'withdrawal.gateway_instant_attempt_created',
            $withdrawRequest,
            $attempt,
            $adminUser,
            $source,
            $failureReason,
            $attempt->gateway_status,
            array_filter([
                'ip' => $ip,
                'approval_mode' => WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT,
                'withdrawal_payout_attempt_id' => $attempt->id,
                'merchant_order_id' => $attempt->merchant_order_id,
            ], fn ($value): bool => $value !== null),
            processingResult: $failureReason ? 'submission_failed' : 'submitted',
        );
    }

    private function ensureGatewayAvailable(): void
    {
        if (! $this->qc168Gateway->isWithdrawalAvailable()) {
            throw ValidationException::withMessages([
                'withdrawal' => __('withdraw.gateway_unavailable'),
            ]);
        }
    }

    private function ensurePending(WithdrawRequest $withdrawRequest): void
    {
        if (! $withdrawRequest->isPending()) {
            throw new RuntimeException('Only pending withdraw requests can be approved.');
        }
    }

    private function withdrawFeePercent(mixed $vipLevel): float
    {
        if ($vipLevel && isset($vipLevel->withdraw_fee_percent)) {
            return round((float) $vipLevel->withdraw_fee_percent, 2);
        }

        return 0.00;
    }

    private function calculateFeeAmount(float $feePercent, float $amount): float
    {
        return round($amount * ($feePercent / 100), 2);
    }
    
        public function cancelScheduledGateway(
        WithdrawRequest $withdrawRequest,
        AdminUser $adminUser,
        string $reason,
        ?string $ip = null,
    ): WithdrawRequest {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'reason' => 'Please enter a reason of 1 to 1000 characters.',
            ]);
        }

        return DB::transaction(function () use (
            $withdrawRequest, $adminUser, $reason, $ip
        ): WithdrawRequest {
            // The dispatcher locks this same row before claiming a payout.
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($adminUser)->authorize('cancelGateway', $lockedRequest);

            if ($lockedRequest->status !== WithdrawRequest::STATUS_APPROVED
                || $lockedRequest->approval_mode !== WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED
                || $lockedRequest->gateway_provider !== WithdrawRequest::GATEWAY_QC168
                || $lockedRequest->payout_status !== WithdrawRequest::PAYOUT_WAITING_RELEASE
                || $lockedRequest->gateway_release_at === null) {
                throw new RuntimeException('This withdrawal is no longer waiting for scheduled release.');
            }

            // A missing submitted_at alone does not prove the request is unsent.
            $submissionFields = [
                'gateway_submitted_at',
                'gateway_completed_at',
                'gateway_last_callback_at',
                'gateway_last_synced_at',
                'gateway_current_merchant_order_id',
                'gateway_payment_status',
                'gateway_final_amount',
            ];

            foreach ($submissionFields as $field) {
                if ($lockedRequest->getAttribute($field) !== null) {
                    throw new RuntimeException('Gateway activity exists. The schedule cannot be cancelled.');
                }
            }

            if ($lockedRequest->payoutAttempts()->exists()
                || $lockedRequest->hasGatewayCancellationLedger()) {
                throw new RuntimeException('A payout attempt or cancellation ledger already exists.');
            }

            $reset = [
                'status' => WithdrawRequest::STATUS_PENDING,
                'approval_mode' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'admin_remark' => null,
                'gateway_provider' => null,
                'payout_status' => null,
                'gateway_release_at' => null,
                'gateway_release_delay_hours_snapshot' => null,
                'gateway_submitted_at' => null,
                'gateway_completed_at' => null,
                'gateway_last_callback_at' => null,
                'gateway_last_synced_at' => null,
                'gateway_current_merchant_order_id' => null,
                'gateway_payment_status' => null,
                'gateway_final_amount' => null,
                'gateway_fail_reason' => null,
                'gateway_meta' => null,
            ];

            $before = array_intersect_key($lockedRequest->getRawOriginal(), $reset);

            // Deliberate, narrowly guarded reset. Query updates bypass model
            // events, so keep the model's normal approval_mode guard unchanged.
            $updated = WithdrawRequest::query()
                ->whereKey($lockedRequest->id)
                ->where('status', WithdrawRequest::STATUS_APPROVED)
                ->where('approval_mode', WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED)
                ->where('gateway_provider', WithdrawRequest::GATEWAY_QC168)
                ->where('payout_status', WithdrawRequest::PAYOUT_WAITING_RELEASE)
                ->whereNotNull('gateway_release_at')
                ->whereNull($submissionFields)
                ->whereDoesntHave('payoutAttempts')
                ->whereDoesntHave('gatewayCancellationTransactions')
                ->update($reset);

            if ($updated !== 1) {
                throw new RuntimeException('The withdrawal changed. Refresh the page and check its status.');
            }

            $lockedRequest->refresh();

            // Preserve approval history without creating any wallet transaction.
            $this->eventRecorder->recordWithdrawal(
                'withdrawal.gateway_schedule_cancelled',
                $lockedRequest,
                actor: $adminUser,
                source: 'filament',
                reason: $reason,
                paymentStatus: WithdrawRequest::STATUS_PENDING,
                payload: [
                    'ip' => $ip,
                    'before' => $before,
                    'after' => $reset,
                ],
            );

            return $lockedRequest;
        }, 3);
    }
}
