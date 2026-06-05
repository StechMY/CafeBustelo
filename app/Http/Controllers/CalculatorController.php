<?php

namespace App\Http\Controllers;

use App\Models\CommissionLevel;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalculatorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        $selectedProductId = $request->integer('product_id');

        $selectedProduct = $products->firstWhere('id', $selectedProductId)
            ?? $products->first();

        $bCount = max(0, (int) $request->query('b_count', 0));
        $cCount = max(0, (int) $request->query('c_count', 0));
        $dCount = max(0, (int) $request->query('d_count', 0));

        $commissionLevels = CommissionLevel::query()
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->keyBy('level');

        $bRate = (float) ($commissionLevels->get(1)?->rate_percent ?? 0);
        $cRate = (float) ($commissionLevels->get(2)?->rate_percent ?? 0);
        $dRate = (float) ($commissionLevels->get(3)?->rate_percent ?? 0);

        $dailyIncome = (float) ($selectedProduct?->daily_income ?? 0);
        $durationDays = (int) ($selectedProduct?->duration_days ?? 0);

        $bDaily = round($dailyIncome * ($bRate / 100) * $bCount, 2);
        $cDaily = round($dailyIncome * ($cRate / 100) * $cCount, 2);
        $dDaily = round($dailyIncome * ($dRate / 100) * $dCount, 2);

        $totalDaily = round($bDaily + $cDaily + $dDaily, 2);
        $monthlyIncome = round($totalDaily * 30, 2);
        $cycleIncome = round($totalDaily * $durationDays, 2);

        $calculatorProducts = $products
            ->map(fn($product): array => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'price' => (float) $product->price,
                'daily_income' => (float) $product->daily_income,
                'duration_days' => (int) $product->duration_days,
            ])
            ->values();

        return view('calculator.index', [
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'calculatorProducts' => $calculatorProducts,

            'rates' => [
                'b' => $bRate,
                'c' => $cRate,
                'd' => $dRate,
            ],
        ]);
    }

}