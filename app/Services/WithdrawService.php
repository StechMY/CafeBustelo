<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WithdrawService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function submit(
        User $user,
        float|string $amount,
        ?string $bankName,
        ?string $bankAccountName,
        ?string $bankAccountNo,
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

        return DB::transaction(function () use (
            $user,
            $amount,
            $bankName,
            $bankAccountName,
            $bankAccountNo,
        ): WithdrawRequest {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            $feeAmount = $this->calculateFeeAmount($lockedUser, $vipLevel, $amount);
            $netAmount = round($amount - $feeAmount, 2);

            if ($netAmount <= 0) {
                throw new RuntimeException(__('withdraw.invalid_net_amount'));
            }

            $withdrawRequest = WithdrawRequest::query()->create([
                'user_id' => $lockedUser->id,
                'amount' => $amount,
                'fee_amount' => $feeAmount,
                'net_amount' => $netAmount,
                'vip_level_snapshot' => $vipLevelNumber,
                'min_withdraw_amount_snapshot' => $minWithdrawAmount,
                'bank_name' => $bankName,
                'bank_account_name' => $bankAccountName,
                'bank_account_no' => $bankAccountNo,
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
                    'fee_amount' => $feeAmount,
                    'net_amount' => $netAmount,
                    'vip_level_snapshot' => $vipLevelNumber,
                    'min_withdraw_amount_snapshot' => $minWithdrawAmount,
                ],
            );

            return $withdrawRequest;
        });
    }

    public function approve(
        WithdrawRequest $withdrawRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
    ): WithdrawRequest {
        return DB::transaction(function () use ($withdrawRequest, $adminUser, $adminRemark): WithdrawRequest {
            $lockedRequest = WithdrawRequest::query()
                ->whereKey($withdrawRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException('Only pending withdraw requests can be approved.');
            }

            $user = $lockedRequest->user()
                ->lockForUpdate()
                ->firstOrFail();

            $this->walletService->consumeLocked(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                amount: $lockedRequest->amount,
            );

            $lockedRequest->forceFill([
                'status' => 'approved',
                'reviewed_by' => $adminUser?->id,
                'reviewed_at' => now(),
                'admin_remark' => $adminRemark,
            ])->save();

            return $lockedRequest;
        });
    }

    public function reject(
        WithdrawRequest $withdrawRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
    ): WithdrawRequest {
        return DB::transaction(function () use ($withdrawRequest, $adminUser, $adminRemark): WithdrawRequest {
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
                    'fee_amount' => $lockedRequest->fee_amount,
                    'net_amount' => $lockedRequest->net_amount,
                    'admin_user_id' => $adminUser?->id,
                ],
            );

            return $lockedRequest;
        });
    }

    private function calculateFeeAmount(User $user, mixed $vipLevel, float $amount): float
    {
        if ($vipLevel && isset($vipLevel->withdraw_fee_percent)) {
            return round($amount * ((float) $vipLevel->withdraw_fee_percent / 100), 2);
        }

        return 0.00;
    }
}