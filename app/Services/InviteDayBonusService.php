<?php

namespace App\Services;

use App\Models\RechargeRequest;
use App\Models\Setting;

class InviteDayBonusService
{
    public function __construct(
        private readonly PointService $pointService,
    ) {}

    public function shouldGiveOnRegistration(): bool
    {
        return ! Setting::bool(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, false);
    }

    public function handleApprovedRecharge(RechargeRequest $rechargeRequest): void
    {
        if (! Setting::bool(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, false)) {
            return;
        }

        if (! $rechargeRequest->isApproved()) {
            return;
        }

        $user = $rechargeRequest->user()->first();

        if (! $user || ! $user->referrer_id) {
            return;
        }

        $hasEarlierApprovedRecharge = RechargeRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereKeyNot($rechargeRequest->id)
            ->exists();

        if ($hasEarlierApprovedRecharge) {
            return;
        }

        $minimumAmount = (float) Setting::decimal(
            PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT,
            0
        );

        if ((float) $rechargeRequest->amount < $minimumAmount) {
            return;
        }

        $referrer = $user->referrer()->first();

        if (! $referrer) {
            return;
        }

        $this->pointService->giveInviteDayRegisterPoints($referrer, $user, [
            'source' => 'first_approved_recharge',
            'recharge_request_id' => $rechargeRequest->id,
            'recharge_amount' => (float) $rechargeRequest->amount,
        ]);
    }
}
