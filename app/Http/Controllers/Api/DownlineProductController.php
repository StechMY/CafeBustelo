<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\User;
use App\Models\UserProduct;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DownlineProductController extends Controller
{
    public function index(Request $request): JsonResponse
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

        $allDownlineIds = collect($levelUserIds)->flatten()->unique()->values()->all();
        $commissionRates = CommissionLevel::query()->whereIn('level', [1, 2, 3])->get()->keyBy('level');
        $userProducts = $allDownlineIds === []
            ? collect()
            : UserProduct::query()
                ->with(['user:id,username,name,phone,referrer_id', 'product:id,name,code'])
                ->whereIn('user_id', $allDownlineIds)
                ->latest('purchased_at')
                ->get();

        $rows = $userProducts->map(function (UserProduct $userProduct) use ($levelUserIds, $commissionRates): array {
            $level = $this->resolveLevel((int) $userProduct->user_id, $levelUserIds);
            $rate = (float) ($commissionRates->get($level)?->rate_percent ?? 0);
            $incomePerClaim = (float) $userProduct->daily_income_snapshot;
            $totalClaims = $userProduct->effectiveTotalClaims();
            $claimedCount = (int) $userProduct->claimed_days;
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
                    1 => 'B', 2 => 'C', 3 => 'D', default => '-'
                },
                'rate' => $rate,
                'product_name' => $userProduct->product?->name ?? data_get($userProduct->meta, 'product_name_snapshot', '-'),
                'product_code' => $userProduct->product?->code ?? data_get($userProduct->meta, 'product_code_snapshot', '-'),
                'status' => $userProduct->status,
                'price' => (float) $userProduct->price_snapshot,
                'income_per_claim' => $incomePerClaim,
                'total_claims' => $totalClaims,
                'claimed_count' => $claimedCount,
                'claim_interval_hours' => $userProduct->effectiveClaimIntervalHours(),
                'estimated_total_income' => round($incomePerClaim * $totalClaims, 2),
                'commission_per_claim' => $commissionPerClaim,
                'claimed_commission_estimate' => $claimedCommissionEstimate,
                'estimated_full_commission' => $estimatedFullCommission,
                'purchased_at' => $userProduct->purchased_at,
            ];
        })->values();

        $groups = $rows->groupBy('user_id')->map(function (Collection $items): array {
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
                'total_sales' => ApiFormatter::money($items->sum('price')),
                'total_income' => ApiFormatter::money($items->sum('estimated_total_income')),
                'total_estimated_commission' => ApiFormatter::money($items->sum('estimated_full_commission')),
                'total_claimed_commission_estimate' => ApiFormatter::money($items->sum('claimed_commission_estimate')),
                'latest_purchase_at' => ApiFormatter::dateTime($items->max('purchased_at')),
                'products' => $items->map(fn (array $row): array => [
                    'product_name' => $row['product_name'],
                    'product_code' => $row['product_code'],
                    'status' => $row['status'],
                    'price' => ApiFormatter::money($row['price']),
                    'income_per_claim' => ApiFormatter::money($row['income_per_claim']),
                    'total_claims' => $row['total_claims'],
                    'claimed_count' => $row['claimed_count'],
                    'claim_interval_hours' => $row['claim_interval_hours'],
                    'estimated_total_income' => ApiFormatter::money($row['estimated_total_income']),
                    'rate' => ApiFormatter::money($row['rate']),
                    'commission_per_claim' => ApiFormatter::money($row['commission_per_claim']),
                    'claimed_commission_estimate' => ApiFormatter::money($row['claimed_commission_estimate']),
                    'estimated_full_commission' => ApiFormatter::money($row['estimated_full_commission']),
                    'purchased_at' => ApiFormatter::dateTime($row['purchased_at']),
                ])->values(),
            ];
        })->sortByDesc('latest_purchase_at')->values();

        $paginator = new LengthAwarePaginator(
            $groups->forPage((int) $request->query('page', 1), (int) $request->query('per_page', 12))->values(),
            $groups->count(),
            (int) $request->query('per_page', 12),
            (int) $request->query('page', 1),
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return ApiResponse::success([
            'summary' => [
                'total_users' => $groups->count(),
                'total_products' => $rows->count(),
                'total_sales' => ApiFormatter::money($rows->sum('price')),
                'total_estimated_commission' => ApiFormatter::money($rows->sum('estimated_full_commission')),
                'total_claimed_commission_estimate' => ApiFormatter::money($rows->sum('claimed_commission_estimate')),
            ],
            'items' => $paginator->getCollection(),
            'pagination' => ['current_page' => $paginator->currentPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total(), 'last_page' => $paginator->lastPage()],
        ]);
    }

    private function directDownlines(User $user): Collection
    {
        return User::query()->where('referrer_id', $user->id)->get();
    }

    private function nestedDownlines(Collection $users): Collection
    {
        $ids = $users->pluck('id')->all();

        return $ids === [] ? collect() : User::query()->whereIn('referrer_id', $ids)->get();
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
}
