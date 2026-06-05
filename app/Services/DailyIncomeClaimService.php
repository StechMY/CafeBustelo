<?php

namespace App\Services;

use App\Models\DailyIncomeClaim;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyIncomeClaimService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly CommissionService $commissionService,
    ) {}

    public function claim(
        User $user,
        UserProduct $userProduct,
        string $source = 'manual_claim',
    ): DailyIncomeClaim {
        return DB::transaction(function () use ($user, $userProduct, $source): DailyIncomeClaim {
            $lockedUserProduct = UserProduct::query()
                ->whereKey($userProduct->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedUserProduct->user_id !== (int) $user->id) {
                throw new RuntimeException(__('daily_income.invalid_product'));
            }

            if ($lockedUserProduct->status !== 'active') {
                throw new RuntimeException(__('daily_income.product_not_active'));
            }

            $now = now();

            if ($lockedUserProduct->expires_at && $now->greaterThanOrEqualTo($lockedUserProduct->expires_at)) {
                $lockedUserProduct->forceFill([
                    'status' => 'expired',
                ])->save();

                $this->deactivateUserIfNoActiveProducts($user);

                throw new RuntimeException(__('daily_income.product_expired'));
            }

            if (! $lockedUserProduct->next_claim_at) {
                throw new RuntimeException(__('daily_income.not_claimable_yet'));
            }

            if ($now->lt($lockedUserProduct->next_claim_at)) {
                throw new RuntimeException(__('daily_income.not_claimable_yet'));
            }

            /**
             * Manual user claim should respect claim window.
             * SVIP auto claim should NOT miss because system may run later.
             */
            if (
                $source !== 'svip_auto_claim'
                && $this->claimWindowExpired($lockedUserProduct->next_claim_at, $now)
            ) {
                $lockedUserProduct->forceFill([
                    'missed_days' => $lockedUserProduct->missed_days + 1,
                    'next_claim_at' => $lockedUserProduct->next_claim_at->copy()->addDay(),
                ])->save();

                throw new RuntimeException(__('daily_income.claim_window_expired'));
            }

            /**
             * For SVIP auto claim:
             * If product has overdue claim dates, auto claim the current next_claim_at one by one.
             * The command will call this repeatedly in later runs until caught up.
             */
            $claimDayNo = $lockedUserProduct->claimed_days + $lockedUserProduct->missed_days + 1;

            if ($claimDayNo > $lockedUserProduct->duration_days_snapshot) {
                $lockedUserProduct->forceFill([
                    'status' => 'expired',
                ])->save();

                $this->deactivateUserIfNoActiveProducts($user);

                throw new RuntimeException(__('daily_income.product_expired'));
            }

            $claimDate = $lockedUserProduct->next_claim_at->toDateString();

            $alreadyClaimed = DailyIncomeClaim::query()
                ->where('user_product_id', $lockedUserProduct->id)
                ->where('claim_date', $claimDate)
                ->exists();

            if ($alreadyClaimed) {
                throw new RuntimeException(__('daily_income.already_claimed'));
            }

            $claim = DailyIncomeClaim::query()->create([
                'user_id' => $user->id,
                'user_product_id' => $lockedUserProduct->id,
                'product_id' => $lockedUserProduct->product_id,
                'claim_day_no' => $claimDayNo,
                'amount' => $lockedUserProduct->daily_income_snapshot,
                'claim_date' => $claimDate,
                'claimable_at' => $lockedUserProduct->next_claim_at,
                'claimed_at' => $now,
                'meta' => [
                    'source' => $source,
                    'is_svip_auto_claim' => $source === 'svip_auto_claim',
                ],
            ]);

            $this->walletService->credit(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'daily_income',
                amount: $lockedUserProduct->daily_income_snapshot,
                reference: $claim,
                remarks: $source === 'svip_auto_claim'
                    ? 'SVIP auto daily income claim'
                    : 'Daily income claim',
                meta: [
                    'user_product_id' => $lockedUserProduct->id,
                    'product_id' => $lockedUserProduct->product_id,
                    'claim_day_no' => $claimDayNo,
                    'source' => $source,
                    'is_svip_auto_claim' => $source === 'svip_auto_claim',
                ],
            );

            $lockedUserProduct->forceFill([
                'claimed_days' => $lockedUserProduct->claimed_days + 1,
                'next_claim_at' => $lockedUserProduct->next_claim_at->copy()->addDay(),
            ])->save();

            $lockedUserProduct->refresh();

            if (
                ($lockedUserProduct->claimed_days + $lockedUserProduct->missed_days)
                >= $lockedUserProduct->duration_days_snapshot
            ) {
                $lockedUserProduct->forceFill([
                    'status' => 'expired',
                    'next_claim_at' => null,
                ])->save();

                $this->deactivateUserIfNoActiveProducts($user);
            }

            $this->commissionService->payFromDailyIncomeClaim(
                sourceUser: $user,
                userProduct: $lockedUserProduct,
                claim: $claim,
            );

            return $claim;
        });
    }

    private function claimWindowExpired(Carbon $claimableAt, Carbon $now): bool
    {
        return $now->copy()->startOfDay()->greaterThan($claimableAt->copy()->startOfDay());
    }

    private function deactivateUserIfNoActiveProducts(User $user): void
    {
        $hasActiveProduct = UserProduct::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $hasActiveProduct) {
            $user->forceFill([
                'status' => 'inactive',
            ])->save();
        }
    }
}