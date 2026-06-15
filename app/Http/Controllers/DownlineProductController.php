<?php

namespace App\Http\Controllers;

use App\Models\CommissionLevel;
use App\Models\User;
use App\Models\UserProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DownlineProductController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $bUsers = $this->directDownlines($user);
        $cUsers = $this->nestedDownlines($bUsers);
        $dUsers = $this->nestedDownlines($cUsers);

        $levelUserIds = [
            1 => $bUsers->pluck('id')->all(),
            2 => $cUsers->pluck('id')->all(),
            3 => $dUsers->pluck('id')->all(),
        ];

        $allDownlineIds = collect($levelUserIds)
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $commissionRates = CommissionLevel::query()
            ->whereIn('level', [1, 2, 3])
            ->get()
            ->keyBy('level');

        $products = UserProduct::query()
            ->with([
                'user:id,username,name,phone,referrer_id',
                'product:id,name,code',
            ])
            ->whereIn('user_id', $allDownlineIds)
            ->latest('purchased_at')
            ->paginate(20)
            ->withQueryString();

        $rows = $products->getCollection()
            ->map(function (UserProduct $userProduct) use ($levelUserIds, $commissionRates): array {
                $level = $this->resolveLevel((int) $userProduct->user_id, $levelUserIds);

                $rate = (float) ($commissionRates->get($level)?->rate_percent ?? 0);

                $price = (float) $userProduct->price_snapshot;

                /*
                 * IMPORTANT:
                 * Commission is NOT based on product price.
                 *
                 * Real rule:
                 * Downline claims income, then upline receives commission from that income.
                 *
                 * Example:
                 * B rate = 11%
                 * Downline claim income = 100
                 * Upline commission = 100 * 11% = 11
                 */
                $incomePerClaim = (float) $userProduct->daily_income_snapshot;
                $totalClaims = $this->effectiveTotalClaims($userProduct);
                $claimedCount = (int) $userProduct->claimed_days;

                $estimatedCommissionPerClaim = round($incomePerClaim * $rate / 100, 2);
                $estimatedFullCycleCommission = round($incomePerClaim * $totalClaims * $rate / 100, 2);
                $claimedCommissionEstimate = round($incomePerClaim * $claimedCount * $rate / 100, 2);

                $estimatedTotalIncome = round($incomePerClaim * $totalClaims, 2);

                return [
                    'user_product' => $userProduct,

                    'level' => $level,
                    'level_label' => match ($level) {
                        1 => 'B',
                        2 => 'C',
                        3 => 'D',
                        default => '-',
                    },

                    'rate' => $rate,

                    // Product purchase price, display only.
                    'price' => $price,

                    // Product income logic.
                    'income_per_claim' => $incomePerClaim,
                    'total_claims' => $totalClaims,
                    'claimed_count' => $claimedCount,
                    'claim_interval_hours' => $this->effectiveClaimIntervalHours($userProduct),
                    'estimated_total_income' => $estimatedTotalIncome,

                    // Commission logic.
                    'commission_per_claim' => $estimatedCommissionPerClaim,
                    'claimed_commission_estimate' => $claimedCommissionEstimate,
                    'earning' => $estimatedFullCycleCommission,
                    'estimated_full_cycle_commission' => $estimatedFullCycleCommission,
                ];
            });

        $products->setCollection($rows);

        $summary = [
            'total_products' => $rows->count(),

            // This is product purchase amount, not commission base.
            'total_sales' => $rows->sum('price'),

            // This is estimated full-cycle commission from income claims.
            'total_estimated_commission' => $rows->sum('earning'),

            'b_products' => $rows->where('level', 1)->count(),
            'c_products' => $rows->where('level', 2)->count(),
            'd_products' => $rows->where('level', 3)->count(),

            'b_estimated_commission' => $rows->where('level', 1)->sum('earning'),
            'c_estimated_commission' => $rows->where('level', 2)->sum('earning'),
            'd_estimated_commission' => $rows->where('level', 3)->sum('earning'),

            'b_commission_per_claim' => $rows->where('level', 1)->sum('commission_per_claim'),
            'c_commission_per_claim' => $rows->where('level', 2)->sum('commission_per_claim'),
            'd_commission_per_claim' => $rows->where('level', 3)->sum('commission_per_claim'),

            'total_claimed_commission_estimate' => $rows->sum('claimed_commission_estimate'),
        ];

        return view('downline-products.index', [
            'user' => $user,
            'products' => $products,
            'summary' => $summary,
            'commissionRates' => $commissionRates,
        ]);
    }

    private function directDownlines(User $user): Collection
    {
        return User::query()
            ->where('referrer_id', $user->id)
            ->get();
    }

    private function nestedDownlines(Collection $users): Collection
    {
        $ids = $users->pluck('id')->all();

        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->whereIn('referrer_id', $ids)
            ->get();
    }

    private function resolveLevel(int $userId, array $levelUserIds): int
    {
        foreach ($levelUserIds as $level => $ids) {
            if (in_array($userId, $ids, true)) {
                return (int) $level;
            }
        }

        return 0;
    }

    private function effectiveClaimIntervalHours(UserProduct $userProduct): int
    {
        if (method_exists($userProduct, 'effectiveClaimIntervalHours')) {
            return $userProduct->effectiveClaimIntervalHours();
        }

        return max(1, (int) ($userProduct->claim_interval_hours_snapshot ?: 24));
    }

    private function effectiveTotalClaims(UserProduct $userProduct): int
    {
        if (method_exists($userProduct, 'effectiveTotalClaims')) {
            return $userProduct->effectiveTotalClaims();
        }

        if ($userProduct->total_claims_snapshot !== null && (int) $userProduct->total_claims_snapshot > 0) {
            return (int) $userProduct->total_claims_snapshot;
        }

        $durationDays = max(1, (int) $userProduct->duration_days_snapshot);
        $claimIntervalHours = $this->effectiveClaimIntervalHours($userProduct);

        return max(1, (int) floor(($durationDays * 24) / $claimIntervalHours));
    }
}