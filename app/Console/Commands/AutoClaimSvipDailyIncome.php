<?php

namespace App\Console\Commands;

use App\Models\SvipPurchase;
use App\Models\User;
use App\Models\UserProduct;
use App\Services\DailyIncomeClaimService;
use Illuminate\Console\Command;
use Throwable;

class AutoClaimSvipDailyIncome extends Command
{
    protected $signature = 'svip:auto-claim-daily-income
                            {--limit=500 : Maximum SVIP users to process per run}
                            {--per-user-product-limit=20 : Maximum products to process per user per run}';

    protected $description = 'Auto claim product daily income for active SVIP users.';

    public function handle(DailyIncomeClaimService $dailyIncomeClaimService): int
    {
        $this->expireOldSvipPurchases();

        $limit = max(1, (int) $this->option('limit'));
        $perUserProductLimit = max(1, (int) $this->option('per-user-product-limit'));

        $processedUsers = 0;
        $processedProducts = 0;
        $claimedCount = 0;
        $failedCount = 0;

        User::query()
            ->where('status', 'active')
            ->whereHas('svipPurchases', function ($query): void {
                $query
                    ->where('status', SvipPurchase::STATUS_ACTIVE)
                    ->where(function ($query): void {
                        $query->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    });
            })
            ->orderBy('id')
            ->limit($limit)
            ->chunkById(100, function ($users) use (
                $dailyIncomeClaimService,
                $perUserProductLimit,
                &$processedUsers,
                &$processedProducts,
                &$claimedCount,
                &$failedCount,
            ): void {
                foreach ($users as $user) {
                    $processedUsers++;

                    $claimableProducts = UserProduct::query()
                        ->where('user_id', $user->id)
                        ->where('status', 'active')
                        ->whereRaw('(claimed_days + missed_days) < COALESCE(total_claims_snapshot, duration_days_snapshot)')
                        ->whereNotNull('next_claim_at')
                        ->where('next_claim_at', '<=', now())
                        ->orderBy('next_claim_at')
                        ->limit($perUserProductLimit)
                        ->get();

                    foreach ($claimableProducts as $userProduct) {
                        $processedProducts++;

                        try {
                            $dailyIncomeClaimService->claim(
                                user: $user,
                                userProduct: $userProduct,
                                source: 'svip_auto_claim',
                            );

                            $claimedCount++;
                        } catch (Throwable $exception) {
                            $failedCount++;

                            report($exception);
                        }
                    }
                }
            });

        $this->info('SVIP auto claim completed.');
        $this->line("Processed users: {$processedUsers}");
        $this->line("Processed products: {$processedProducts}");
        $this->line("Claims created: {$claimedCount}");
        $this->line("Failed claims: {$failedCount}");

        return self::SUCCESS;
    }

    private function expireOldSvipPurchases(): void
    {
        SvipPurchase::query()
            ->where('status', SvipPurchase::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => SvipPurchase::STATUS_EXPIRED,
                'updated_at' => now(),
            ]);
    }
}