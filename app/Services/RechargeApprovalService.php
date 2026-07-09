<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\RechargeRequest;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RechargeApprovalService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly InviteDayBonusService $inviteDayBonusService,
    ) {}

    public function approve(
        RechargeRequest $rechargeRequest,
        ?AdminUser $adminUser,
        ?string $adminRemark = null,
    ): RechargeRequest {
        $approvedRechargeRequest = DB::transaction(function () use ($rechargeRequest, $adminUser, $adminRemark): RechargeRequest {
            $lockedRequest = RechargeRequest::query()
                ->whereKey($rechargeRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRequest->isPending()) {
                throw new RuntimeException('Only pending recharge requests can be approved.');
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
                ],
            );

            $user->increment('accumulated_recharge', (float) $lockedRequest->amount);

            return $lockedRequest;
        });

        $this->inviteDayBonusService->handleApprovedRecharge($approvedRechargeRequest);

        return $approvedRechargeRequest;
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
