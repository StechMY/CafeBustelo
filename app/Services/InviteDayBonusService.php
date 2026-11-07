<?php

namespace App\Services;

use App\Models\RechargeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProduct;

class InviteDayBonusService
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function handlePurchase(User $user, UserProduct $purchase): void
    {
        if (! $user->created_at || ! $purchase->purchased_at
            || ! $user->created_at->isSameDay($purchase->purchased_at)) {
            return;
        }

        $referrer = $user->referrer()->first();

        if (! $referrer) {
            return;
        }

        $meta = [
            'source' => 'product_purchase',
            'user_product_id' => $purchase->id,
            'product_id' => $purchase->product_id,
        ];

        if (Setting::bool(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, false)) {
            // Ledger IDs preserve approval order even when timestamps match.
            $firstDeposit = $user->walletTransactions()
                ->where('type', 'recharge_approved')
                ->whereHasMorph('reference', [RechargeRequest::class], fn ($query) => $query->where('status', 'approved'))
                ->orderBy('id')
                ->first()?->reference;

            if (! $firstDeposit?->reviewed_at
                || ! $user->created_at->isSameDay($firstDeposit->reviewed_at)
                || (float) $firstDeposit->amount < Setting::decimal(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 0)) {
                return;
            }

            $meta['recharge_request_id'] = $firstDeposit->id;
            $meta['recharge_amount'] = (float) $firstDeposit->amount;
        }

        // Purchase holds the buyer's row lock; reuse the legacy reward for deduplication.
        $this->pointService->giveInviteDayRegisterPoints($referrer, $user, $meta);
    }
}
