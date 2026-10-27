<?php

namespace App\Services;

use App\Models\CommissionLevel;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;

class CommissionService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function payFromDailyIncomeClaim(
        User $sourceUser,
        UserProduct $userProduct,
        DailyIncomeClaim $claim,
    ): void {
        $commissionLevels = CommissionLevel::query()
            ->where('is_active', true)
            ->where('rate_percent', '>', 0)
            ->orderBy('level')
            ->get();

        if ($commissionLevels->isEmpty()) {
            return;
        }

        $currentUpline = $sourceUser->referrer;

        foreach ($commissionLevels as $commissionLevel) {
            if (! $currentUpline) {
                break;
            }

            $this->tryPayCommission(
                earner: $currentUpline,
                sourceUser: $sourceUser,
                userProduct: $userProduct,
                claim: $claim,
                generationLevel: (int) $commissionLevel->level,
                percentage: (float) $commissionLevel->rate_percent,
                commissionLevelName: $commissionLevel->name,
            );

            $currentUpline = $currentUpline->referrer;
        }
    }

    private function tryPayCommission(
        User $earner,
        User $sourceUser,
        UserProduct $userProduct,
        DailyIncomeClaim $claim,
        int $generationLevel,
        float $percentage,
        ?string $commissionLevelName = null,
    ): void {
        if ($earner->status !== 'active') {
            return;
        }

        $earnerHighestActiveProductPrice = UserProduct::query()
            ->where('user_id', $earner->id)
            ->where('status', 'active')
            ->get(['price_snapshot', 'meta'])
            ->max(fn (UserProduct $product): float => (float) ($product->meta['original_price'] ?? $product->price_snapshot));

        if ($earnerHighestActiveProductPrice === null) {
            return;
        }

        $sourceProductPrice = (float) ($userProduct->meta['original_price'] ?? $userProduct->price_snapshot);

        /**
         * Rule:
         * Earner's highest active product must be >= source product price.
         * Use purchase-time original prices so coupons do not change product tiers.
         *
         * Example:
         * - Earner P3, downline claims P5 => no commission.
         * - Earner P5, downline claims P3 => can receive.
         * - Earner P3, downline claims P3 => can receive.
         */
        if ((float) $earnerHighestActiveProductPrice < $sourceProductPrice) {
            return;
        }

        $alreadyPaid = CommissionRecord::query()
            ->where('earner_user_id', $earner->id)
            ->where('daily_income_claim_id', $claim->id)
            ->where('generation_level', $generationLevel)
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        $sourceDailyIncome = (float) $claim->amount;

        $amount = round(($sourceDailyIncome * $percentage) / 100, 2);

        if ($amount <= 0) {
            return;
        }

        $commission = CommissionRecord::query()->create([
            'earner_user_id' => $earner->id,
            'source_user_id' => $sourceUser->id,
            'source_user_product_id' => $userProduct->id,
            'daily_income_claim_id' => $claim->id,
            'product_id' => $userProduct->product_id,

            'generation_level' => $generationLevel,
            'source_daily_income' => $sourceDailyIncome,

            // Snapshot the admin-configured rate at payout time.
            'percentage' => $percentage,

            'amount' => $amount,
            'status' => 'paid',
            'meta' => [
                'source' => 'daily_income_claim',
                'commission_level_name' => $commissionLevelName,
                'source_product_price_snapshot' => (float) $userProduct->price_snapshot,
                'source_product_qualification_price' => $sourceProductPrice,
                'earner_highest_active_product_price' => (float) $earnerHighestActiveProductPrice,
            ],
        ]);

        $this->walletService->credit(
            user: $earner,
            walletType: Wallet::TYPE_WITHDRAWABLE,
            transactionType: 'commission',
            amount: $amount,
            reference: $commission,
            remarks: 'Commission from ' . $sourceUser->username,
            meta: [
                'commission_record_id' => $commission->id,
                'source_user_id' => $sourceUser->id,
                'source_username' => $sourceUser->username,
                'source_user_product_id' => $userProduct->id,
                'daily_income_claim_id' => $claim->id,
                'generation_level' => $generationLevel,
                'commission_level_name' => $commissionLevelName,
                'percentage' => $percentage,
                'source_daily_income' => $sourceDailyIncome,
            ],
        );
    }
}
