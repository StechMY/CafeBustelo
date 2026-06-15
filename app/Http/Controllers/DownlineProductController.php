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
                $earning = round($price * $rate / 100, 2);

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
                    'price' => $price,
                    'earning' => $earning,
                ];
            });

        $products->setCollection($rows);

        $summary = [
            'total_products' => $rows->count(),
            'total_sales' => $rows->sum('price'),
            'total_estimated_commission' => $rows->sum('earning'),

            'b_products' => $rows->where('level', 1)->count(),
            'c_products' => $rows->where('level', 2)->count(),
            'd_products' => $rows->where('level', 3)->count(),

            'b_estimated_commission' => $rows->where('level', 1)->sum('earning'),
            'c_estimated_commission' => $rows->where('level', 2)->sum('earning'),
            'd_estimated_commission' => $rows->where('level', 3)->sum('earning'),
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
}