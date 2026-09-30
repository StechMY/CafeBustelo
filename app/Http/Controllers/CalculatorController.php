<?php

namespace App\Http\Controllers;

use App\Models\CommissionLevel;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        $commissionLevels = CommissionLevel::query()
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->keyBy('level');

        $bRate = (float) ($commissionLevels->get(1)?->rate_percent ?? 0);
        $cRate = (float) ($commissionLevels->get(2)?->rate_percent ?? 0);
        $dRate = (float) ($commissionLevels->get(3)?->rate_percent ?? 0);

        $calculatorProducts = $products
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,

                'product_type' => $product->product_type,
                'type_label' => $this->productTypeLabel($product),

                'price' => (float) $product->price,
                'daily_income' => (float) $product->daily_income,
                'duration_days' => (int) $product->duration_days,

                // For normal product:
                // total_claims = duration_days when total_claims is empty.
                //
                // For hourly / special product:
                // total_claims can override duration_days.
                'claim_interval_hours' => $product->effectiveClaimIntervalHours(),
                'total_claims' => $product->effectiveTotalClaims(),

                'extra_points' => (int) ($product->extra_points ?? 0),

                'available_days' => $product->available_days ?: [],
                'available_days_label' => $this->availableDaysLabel($product),

                'available_from' => $product->available_from?->format('H:i'),
                'available_until' => $product->available_until?->format('H:i'),

                'stock_quantity' => $product->stock_quantity,
                'sold_quantity' => (int) ($product->sold_quantity ?? 0),
                'remaining_stock' => $product->stock_quantity === null
                    ? null
                    : max(0, (int) $product->stock_quantity - (int) $product->sold_quantity),

                'is_purchasable_now' => $product->isPurchasableNow(),
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

    private function productTypeLabel(Product $product): string
    {
        $key = 'products.types.' . $product->product_type;
        $label = __($key);

        return $label !== $key
            ? $label
            : Str::of((string) $product->product_type)->headline()->toString();
    }

    private function availableDaysLabel(Product $product): string
    {
        $days = $product->available_days ?: [];

        if ($days === []) {
            return __('filament.everyday');
        }

        return collect($days)
            ->map(fn ($day): string => Product::weekdayOptions()[(int) $day] ?? (string) $day)
            ->join(', ');
    }
}