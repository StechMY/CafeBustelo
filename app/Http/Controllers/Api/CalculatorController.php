<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\Product;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;

class CalculatorController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $products = Product::query()->where('status', 'active')->orderBy('sort_order')->orderBy('price')->get();
        $rates = CommissionLevel::query()->where('is_active', true)->orderBy('level')->get()->keyBy('level');

        return ApiResponse::success([
            'products' => $products->map(fn (Product $product): array => ApiFormatter::product($product))->values(),
            'rates' => [
                'b' => ApiFormatter::money($rates->get(1)?->rate_percent),
                'c' => ApiFormatter::money($rates->get(2)?->rate_percent),
                'd' => ApiFormatter::money($rates->get(3)?->rate_percent),
            ],
            'formula' => 'commission = income_per_claim * rate / 100',
        ]);
    }
}
