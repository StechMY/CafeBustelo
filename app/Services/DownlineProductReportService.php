<?php

namespace App\Services;

use App\Models\CommissionLevel;
use App\Models\User;
use App\Models\UserProduct;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DownlineProductReportService
{
    public function reportForUser(User $user): array
    {
        $bUsers = $this->directDownlines($user);
        $cUsers = $this->nestedDownlines($bUsers);
        $dUsers = $this->nestedDownlines($cUsers);

        $levelUserIds = [
            1 => $bUsers->pluck('id')->all(),
            2 => $cUsers->pluck('id')->all(),
            3 => $dUsers->pluck('id')->all(),
        ];

        $commissionRates = CommissionLevel::query()
            ->whereIn('level', [1, 2, 3])
            ->get()
            ->keyBy('level');

        $allDownlineIds = collect($levelUserIds)
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $userProducts = $allDownlineIds === []
            ? collect()
            : UserProduct::query()
                ->with([
                    'user:id,username,name,phone,referrer_id',
                    'product:id,name,code',
                ])
                ->whereIn('user_id', $allDownlineIds)
                ->latest('purchased_at')
                ->get();

        $rows = $this->rowsForProducts($userProducts, $levelUserIds, $commissionRates);
        $groups = $this->groupsForRows($rows);

        return [
            'rows' => $rows,
            'groups' => $groups,
            'summary' => $this->summaryForGroups($groups, $rows),
            'commission_rates' => $commissionRates,
        ];
    }

    public function groupsForUser(User $user): Collection
    {
        return $this->reportForUser($user)['groups'];
    }

    public function summaryForGroups(Collection $groups, ?Collection $rows = null): array
    {
        $rows ??= $groups
            ->flatMap(fn (array $group): array => $group['products'])
            ->values();

        return [
            'total_users' => $groups->count(),
            'total_products' => $rows->count(),
            'total_sales' => round((float) $rows->sum('price'), 2),
            'total_estimated_commission' => round((float) $rows->sum('earning'), 2),
            'total_claimed_commission_estimate' => round((float) $rows->sum('claimed_commission_estimate'), 2),

            'b_users' => $groups->where('level', 1)->count(),
            'c_users' => $groups->where('level', 2)->count(),
            'd_users' => $groups->where('level', 3)->count(),

            'b_products' => $rows->where('level', 1)->count(),
            'c_products' => $rows->where('level', 2)->count(),
            'd_products' => $rows->where('level', 3)->count(),

            'b_estimated_commission' => round((float) $rows->where('level', 1)->sum('earning'), 2),
            'c_estimated_commission' => round((float) $rows->where('level', 2)->sum('earning'), 2),
            'd_estimated_commission' => round((float) $rows->where('level', 3)->sum('earning'), 2),

            'b_commission_per_claim' => round((float) $rows->where('level', 1)->sum('commission_per_claim'), 2),
            'c_commission_per_claim' => round((float) $rows->where('level', 2)->sum('commission_per_claim'), 2),
            'd_commission_per_claim' => round((float) $rows->where('level', 3)->sum('commission_per_claim'), 2),
        ];
    }

    public function paginateGroups(
        Collection $groups,
        int $perPage,
        int $page,
        string $path,
        array $query = [],
    ): LengthAwarePaginator {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        return new LengthAwarePaginator(
            items: $groups->forPage($page, $perPage)->values(),
            total: $groups->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $path,
                'query' => $query,
            ],
        );
    }

    public function effectiveTotalClaims(UserProduct $userProduct): int
    {
        if (method_exists($userProduct, 'effectiveTotalClaims')) {
            return $userProduct->effectiveTotalClaims();
        }

        if ($userProduct->total_claims_snapshot !== null && (int) $userProduct->total_claims_snapshot > 0) {
            return (int) $userProduct->total_claims_snapshot;
        }

        $durationDays = max(1, (int) $userProduct->duration_days_snapshot);

        return max(1, (int) floor(($durationDays * 24) / $this->effectiveClaimIntervalHours($userProduct)));
    }

    public function effectiveClaimIntervalHours(UserProduct $userProduct): int
    {
        if (method_exists($userProduct, 'effectiveClaimIntervalHours')) {
            return $userProduct->effectiveClaimIntervalHours();
        }

        return max(1, (int) ($userProduct->claim_interval_hours_snapshot ?: 24));
    }

    public function resolveLevel(int $userId, array $levelUserIds): int
    {
        foreach ($levelUserIds as $level => $ids) {
            if (in_array($userId, $ids, true)) {
                return (int) $level;
            }
        }

        return 0;
    }

    private function rowsForProducts(
        Collection $userProducts,
        array $levelUserIds,
        Collection $commissionRates,
    ): Collection {
        return $userProducts
            ->map(function (UserProduct $userProduct) use ($levelUserIds, $commissionRates): array {
                $level = $this->resolveLevel((int) $userProduct->user_id, $levelUserIds);
                $rate = (float) ($commissionRates->get($level)?->rate_percent ?? 0);
                $price = (float) $userProduct->price_snapshot;
                $incomePerClaim = (float) $userProduct->daily_income_snapshot;
                $totalClaims = $this->effectiveTotalClaims($userProduct);
                $claimedCount = (int) $userProduct->claimed_days;
                $claimIntervalHours = $this->effectiveClaimIntervalHours($userProduct);
                $commissionPerClaim = round($incomePerClaim * $rate / 100, 2);
                $estimatedFullCommission = round($incomePerClaim * $totalClaims * $rate / 100, 2);
                $claimedCommissionEstimate = round($incomePerClaim * $claimedCount * $rate / 100, 2);

                return [
                    'user_id' => (int) $userProduct->user_id,
                    'username' => $userProduct->user?->username ?? '-',
                    'name' => $userProduct->user?->name ?? '-',
                    'phone' => $userProduct->user?->phone ?? '-',
                    'level' => $level,
                    'level_label' => match ($level) {
                        1 => 'B',
                        2 => 'C',
                        3 => 'D',
                        default => '-',
                    },
                    'rate' => $rate,
                    'product_name' => $userProduct->product?->name ?? data_get($userProduct->meta, 'product_name_snapshot', '-'),
                    'product_code' => $userProduct->product?->code ?? data_get($userProduct->meta, 'product_code_snapshot', '-'),
                    'status' => $userProduct->status,
                    'price' => round($price, 2),
                    'income_per_claim' => round($incomePerClaim, 2),
                    'total_claims' => $totalClaims,
                    'claimed_count' => $claimedCount,
                    'claim_interval_hours' => $claimIntervalHours,
                    'estimated_total_income' => round($incomePerClaim * $totalClaims, 2),
                    'commission_per_claim' => $commissionPerClaim,
                    'claimed_commission_estimate' => $claimedCommissionEstimate,
                    'earning' => $estimatedFullCommission,
                    'estimated_full_cycle_commission' => $estimatedFullCommission,
                    'purchased_at' => $userProduct->purchased_at,
                    'purchased_at_label' => $userProduct->purchased_at?->format('d M Y, h:i A') ?? '-',
                ];
            })
            ->values();
    }

    private function groupsForRows(Collection $rows): Collection
    {
        return $rows
            ->groupBy('user_id')
            ->map(function (Collection $items): array {
                $first = $items->first();
                $latestPurchaseAt = $items->max('purchased_at');

                return [
                    'user_id' => $first['user_id'],
                    'username' => $first['username'],
                    'name' => $first['name'],
                    'phone' => $first['phone'],
                    'level' => $first['level'],
                    'level_label' => $first['level_label'],
                    'rate' => $first['rate'],
                    'product_count' => $items->count(),
                    'total_sales' => round((float) $items->sum('price'), 2),
                    'total_income' => round((float) $items->sum('estimated_total_income'), 2),
                    'total_estimated_commission' => round((float) $items->sum('earning'), 2),
                    'total_claimed_commission_estimate' => round((float) $items->sum('claimed_commission_estimate'), 2),
                    'latest_purchase_at' => $latestPurchaseAt,
                    'latest_purchase_at_label' => optional($latestPurchaseAt)->format('d M Y, h:i A') ?? '-',
                    'products' => $items->values()->all(),
                ];
            })
            ->sortByDesc('latest_purchase_at')
            ->values();
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

        return $ids === []
            ? collect()
            : User::query()->whereIn('referrer_id', $ids)->get();
    }
}
