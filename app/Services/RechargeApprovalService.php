<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\PointTransaction;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RechargeApprovalService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function approve(
        RechargeRequest $rechargeRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
        array $meta = [],
    ): RechargeRequest {
        return DB::transaction(function () use ($rechargeRequest, $adminUser, $adminRemark, $meta): RechargeRequest {
            $lockedRequest = RechargeRequest::query()
                ->whereKey($rechargeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException('Only pending recharge requests can be approved.');
            }
            if ($lockedRequest->gateway_provider === 'ydpay'
                && ($adminUser !== null || $lockedRequest->gateway_payment_status !== 'completed'
                    || ($meta['gateway_provider'] ?? null) !== 'ydpay')) {
                throw new RuntimeException('YDPAY deposits require verified provider completion.');
            }

            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRequest->forceFill([
                'status' => 'approved',
                'reviewed_by' => $adminUser?->id,
                'reviewed_at' => now(),
                'admin_remark' => $adminRemark,
            ])->save();

            $this->walletService->credit(
                user: $user,
                walletType: Wallet::TYPE_RECHARGE,
                transactionType: 'recharge_approved',
                amount: $lockedRequest->amount,
                reference: $lockedRequest,
                remarks: 'Recharge request approved',
                meta: [
                    'recharge_request_id' => $lockedRequest->id,
                    'admin_user_id' => $adminUser?->id,
                    ...$meta,
                ],
            );

            $user->increment('accumulated_recharge', (float) $lockedRequest->amount);

            return $lockedRequest;
        });
    }

    public function reapproveManual(
        RechargeRequest $request,
        AdminUser $admin,
        string $confirmedAmount,
        bool $confirmed,
        string $reason,
        ?string $ip = null,
    ): RechargeRequest {
        return DB::transaction(function () use ($request, $admin, $confirmedAmount, $confirmed, $reason, $ip): RechargeRequest {
            $request = RechargeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('audit', $request);
            abort_unless($admin->canAccessUserRecord($request->user), 403);
            $this->ensureManual($request);
            $this->validateConfirmation($request, $confirmedAmount, $confirmed);
            $reason = trim($reason);
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();
            if (! $request->isRejected()) {
                throw new RuntimeException(__('recharge.reapproval_not_rejected'));
            }

            $entries = WalletTransaction::query()->where('reference_type', RechargeRequest::class)
                ->where('reference_id', $request->id)->orderBy('id')->lockForUpdate()->get();
            // A rejected request may have a fully reversed original approval.
            if ($entries->isNotEmpty()) {
                $credit = $entries->first();
                $reversal = $entries->last();
                if ($entries->count() !== 2 || $credit->type !== 'recharge_approved'
                    || $credit->direction !== WalletTransaction::DIRECTION_CREDIT
                    || $reversal->type !== 'recharge_reversed' || $reversal->direction !== WalletTransaction::DIRECTION_DEBIT
                    || (int) ($reversal->meta['original_transaction_id'] ?? 0) !== $credit->id
                    || $credit->wallet_id !== $reversal->wallet_id
                    || $entries->contains(fn ($entry) => $entry->user_id !== $request->user_id
                        || $entry->wallet_type !== Wallet::TYPE_RECHARGE || $entry->amount !== $request->amount)
                    || round((float) $credit->balance_after - (float) $credit->balance_before, 2) !== (float) $request->amount
                    || round((float) $reversal->balance_before - (float) $reversal->balance_after, 2) !== (float) $request->amount) {
                    throw new RuntimeException(__('recharge.reapproval_inconsistent'));
                }
            }
            $meta = [
                'reapproved' => true,
                'previous_reviewed_by' => $request->reviewed_by,
                'previous_reviewed_at' => $request->reviewed_at?->toIso8601String(),
                'previous_admin_remark' => $request->admin_remark,
                'reason' => $reason,
                'ip' => $ip,
            ];
            // Reuse the normal credit and accumulated-recharge update atomically.
            $request->forceFill(['status' => 'pending'])->save();

            return $this->approve($request, $admin, $reason, $meta);
        });
    }

    public function reverseManualApproval(
        RechargeRequest $request,
        AdminUser $admin,
        string $confirmedAmount,
        bool $confirmed,
        string $reason,
        ?string $ip = null,
    ): RechargeRequest {
        return DB::transaction(function () use ($request, $admin, $confirmedAmount, $confirmed, $reason, $ip): RechargeRequest {
            $request = RechargeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($admin)->authorize('reverseApproval', $request);
            abort_unless($admin->canAccessUserRecord($request->user), 403);
            $this->ensureManual($request);
            $this->validateConfirmation($request, $confirmedAmount, $confirmed);
            $reason = trim($reason);
            Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();

            if (! $request->isApproved()) {
                throw new RuntimeException(__('recharge.reversal_not_approved'));
            }

            $user = $request->user()->lockForUpdate()->firstOrFail();
            $wallet = Wallet::query()->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_RECHARGE)->lockForUpdate()->first();
            $entries = WalletTransaction::query()
                ->where('reference_type', RechargeRequest::class)
                ->where('reference_id', $request->id)->lockForUpdate()->get();
            $credit = $entries->first();

            if (! $wallet || $entries->count() !== 1 || ! $credit
                || $credit->type !== 'recharge_approved'
                || $credit->direction !== WalletTransaction::DIRECTION_CREDIT
                || $credit->wallet_id !== $wallet->id || $credit->user_id !== $user->id
                || $credit->wallet_type !== Wallet::TYPE_RECHARGE
                || $credit->amount !== $request->amount
                || round((float) $credit->balance_after - (float) $credit->balance_before, 2) !== round((float) $request->amount, 2)) {
                throw new RuntimeException(__('recharge.reversal_inconsistent'));
            }

            $latest = WalletTransaction::query()->where('wallet_id', $wallet->id)->latest('id')->first();
            if ($latest?->balance_after !== $wallet->balance
                || (float) $user->accumulated_recharge < (float) $request->amount) {
                throw new RuntimeException(__('recharge.reversal_inconsistent'));
            }

            if ((float) $wallet->balance < (float) $request->amount) {
                throw new RuntimeException(__('recharge.reversal_insufficient'));
            }

            // Reject spent/adjusted funds even if a later deposit restored the balance.
            if ((float) $wallet->locked_balance > 0 || WalletTransaction::query()
                ->where('wallet_id', $wallet->id)->where('id', '>', $credit->id)
                ->where('direction', WalletTransaction::DIRECTION_DEBIT)
                ->where('type', '!=', 'recharge_reversed')->exists()
                || UserProduct::withTrashed()->where('user_id', $user->id)
                    ->where('purchased_at', '>=', $credit->created_at)->exists()) {
                throw new RuntimeException(__('recharge.reversal_used'));
            }

            if (PointTransaction::query()->where('meta->recharge_request_id', $request->id)->exists()
                || PointTransaction::query()->where('reference_type', $user::class)
                    ->where('reference_id', $user->id)
                    ->where('type', PointTransaction::TYPE_INVITE_DAY_FIRST_PURCHASE)
                    ->where('created_at', '>=', $credit->created_at)->exists()
                || SalaryClaim::query()->where('user_id', $user->id)
                    ->where('claimed_at', '>=', $credit->created_at)->exists()) {
                throw new RuntimeException(__('recharge.reversal_rewards'));
            }

            $this->walletService->debit(
                user: $user,
                walletType: Wallet::TYPE_RECHARGE,
                transactionType: 'recharge_reversed',
                amount: $request->amount,
                reference: $request,
                remarks: $reason,
                meta: [
                    'original_transaction_id' => $credit->id,
                    'original_reviewed_by' => $request->reviewed_by,
                    'original_reviewed_at' => $request->reviewed_at?->toIso8601String(),
                    'original_admin_remark' => $request->admin_remark,
                    'accumulated_recharge_before' => $user->accumulated_recharge,
                    'admin_user_id' => $admin->id,
                    'reason' => $reason,
                    'ip' => $ip,
                ],
            );
            $user->decrement('accumulated_recharge', (float) $request->amount);
            $request->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_remark' => __('recharge.reversed_remark', ['reason' => $reason]),
            ])->save();

            return $request;
        });
    }

    private function ensureManual(RechargeRequest $request): void
    {
        if (! $request->isManualDeposit() || filled($request->gateway_provider)
            || filled($request->gateway_order_uid) || filled($request->gateway_payment_uid)
            || filled($request->merchant_order_id)) {
            throw new RuntimeException(__('recharge.manual_only'));
        }
    }

    private function validateConfirmation(RechargeRequest $request, string $amount, bool $confirmed): void
    {
        Validator::make([
            'confirmed_amount' => $amount,
            'confirmed' => $confirmed,
        ], [
            'confirmed_amount' => ['required', 'regex:/^\d{1,12}(\.\d{1,2})?$/'],
            'confirmed' => ['accepted'],
        ])->validate();

        if ((float) $amount <= 0 || number_format((float) $amount, 2, '.', '') !== $request->amount) {
            throw ValidationException::withMessages(['confirmed_amount' => __('recharge.amount_mismatch')]);
        }
    }

    public function reject(
        RechargeRequest $rechargeRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
    ): RechargeRequest {
        return DB::transaction(function () use ($rechargeRequest, $adminUser, $adminRemark): RechargeRequest {
            $lockedRequest = RechargeRequest::query()
                ->whereKey($rechargeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException('Only pending recharge requests can be rejected.');
            }

            $lockedRequest->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $adminUser?->id,
                'reviewed_at' => now(),
                'admin_remark' => $adminRemark,
            ])->save();

            return $lockedRequest;
        });
    }
}
