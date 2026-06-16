<?php

namespace App\Http\Controllers;

use App\Models\CommissionLevel;
use App\Models\User;
use App\Models\UserProduct;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $userProducts = collect();

        if ($allDownlineIds !== []) {
            $userProducts = UserProduct::query()
                ->with([
                    'user:id,username,name,phone,referrer_id',
                    'product:id,name,code',
                ])
                ->whereIn('user_id', $allDownlineIds)
                ->latest('purchased_at')
                ->get();
        }

        $rows = $userProducts
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
                $estimatedTotalIncome = round($incomePerClaim * $totalClaims, 2);

                return [
                    'user_product' => $userProduct,

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

                    'price' => $price,
                    'income_per_claim' => $incomePerClaim,
                    'total_claims' => $totalClaims,
                    'claimed_count' => $claimedCount,
                    'claim_interval_hours' => $claimIntervalHours,
                    'estimated_total_income' => $estimatedTotalIncome,

                    'commission_per_claim' => $commissionPerClaim,
                    'claimed_commission_estimate' => $claimedCommissionEstimate,
                    'earning' => $estimatedFullCommission,
                    'estimated_full_cycle_commission' => $estimatedFullCommission,

                    'purchased_at' => $userProduct->purchased_at,
                    'purchased_at_label' => $userProduct->purchased_at?->format('d M Y, h:i A') ?? '-',
                    'status' => $userProduct->status,
                ];
            })
            ->values();

        $groups = $rows
            ->groupBy('user_id')
            ->map(function (Collection $items): array {
                $first = $items->first();

                return [
                    'user_id' => $first['user_id'],
                    'username' => $first['username'],
                    'name' => $first['name'],
                    'phone' => $first['phone'],
                    'level' => $first['level'],
                    'level_label' => $first['level_label'],
                    'rate' => $first['rate'],

                    'product_count' => $items->count(),
                    'total_sales' => $items->sum('price'),
                    'total_income' => $items->sum('estimated_total_income'),
                    'total_estimated_commission' => $items->sum('earning'),
                    'total_claimed_commission_estimate' => $items->sum('claimed_commission_estimate'),

                    'latest_purchase_at' => $items->max('purchased_at'),
                    'latest_purchase_at_label' => optional($items->max('purchased_at'))->format('d M Y, h:i A') ?? '-',

                    'products' => $items
                        ->map(fn (array $row): array => [
                            'product_name' => $row['product_name'],
                            'product_code' => $row['product_code'],
                            'status' => $row['status'],
                            'price' => $row['price'],
                            'income_per_claim' => $row['income_per_claim'],
                            'total_claims' => $row['total_claims'],
                            'claimed_count' => $row['claimed_count'],
                            'claim_interval_hours' => $row['claim_interval_hours'],
                            'estimated_total_income' => $row['estimated_total_income'],
                            'rate' => $row['rate'],
                            'commission_per_claim' => $row['commission_per_claim'],
                            'claimed_commission_estimate' => $row['claimed_commission_estimate'],
                            'earning' => $row['earning'],
                            'purchased_at_label' => $row['purchased_at_label'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('latest_purchase_at')
            ->values();

        $groupsPaginator = $this->paginateCollection(
            items: $groups,
            perPage: 12,
            page: LengthAwarePaginator::resolveCurrentPage(),
            path: $request->url(),
            query: $request->query(),
        );

        $summary = [
            'total_users' => $groups->count(),
            'total_products' => $rows->count(),
            'total_sales' => $rows->sum('price'),
            'total_estimated_commission' => $rows->sum('earning'),
            'total_claimed_commission_estimate' => $rows->sum('claimed_commission_estimate'),

            'b_users' => $groups->where('level', 1)->count(),
            'c_users' => $groups->where('level', 2)->count(),
            'd_users' => $groups->where('level', 3)->count(),

            'b_products' => $rows->where('level', 1)->count(),
            'c_products' => $rows->where('level', 2)->count(),
            'd_products' => $rows->where('level', 3)->count(),

            'b_estimated_commission' => $rows->where('level', 1)->sum('earning'),
            'c_estimated_commission' => $rows->where('level', 2)->sum('earning'),
            'd_estimated_commission' => $rows->where('level', 3)->sum('earning'),

            'b_commission_per_claim' => $rows->where('level', 1)->sum('commission_per_claim'),
            'c_commission_per_claim' => $rows->where('level', 2)->sum('commission_per_claim'),
            'd_commission_per_claim' => $rows->where('level', 3)->sum('commission_per_claim'),
        ];

        return view('downline-products.index', [
            'user' => $user,
            'groups' => $groupsPaginator,
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

    private function paginateCollection(
        Collection $items,
        int $perPage,
        int $page,
        string $path,
        array $query = [],
    ): LengthAwarePaginator {
        return new LengthAwarePaginator(
            items: $items->forPage($page, $perPage)->values(),
            total: $items->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $path,
                'query' => $query,
            ],
        );
    }
}