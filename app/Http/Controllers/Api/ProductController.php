<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PurchaseRequest;
use App\Models\Product;
use App\Models\UserProduct;
use App\Services\DailyIncomeClaimService;
use App\Services\ProductCatalogService;
use App\Services\ProductPurchaseService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request, ProductCatalogService $productCatalogService): JsonResponse
    {
        $catalog = $productCatalogService->groupedAvailableProducts();

        return ApiResponse::success([
            'tabs' => $catalog['productTypeTabs'],
            'products_by_type' => $catalog['productsByType']
                ->map(fn ($products) => $products
                    ->map(fn (Product $product): array => ApiFormatter::product($product, $request->user()))
                    ->values())
                ->all(),
            'items' => $catalog['products']->map(fn (Product $product): array => ApiFormatter::product($product, $request->user()))->values(),
        ]);
    }

    public function purchase(PurchaseRequest $request, Product $product, ProductPurchaseService $service): JsonResponse
    {
        try {
            $userProduct = $service->purchase($request->user(), $product, $request->validated('wallet_type'));

            return ApiResponse::success([
                'user_product' => ApiFormatter::userProduct($userProduct->load('product')),
            ], __('products.purchase_success'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('products.purchase_failed'), [], 500);
        }
    }

    public function userProducts(Request $request): JsonResponse
    {
        $items = UserProduct::query()
            ->with('product')
            ->where('user_id', $request->user()->id)
            ->latest('purchased_at')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($items, fn (UserProduct $item): array => ApiFormatter::userProduct($item));
    }

    public function claim(Request $request, UserProduct $userProduct, DailyIncomeClaimService $service): JsonResponse
    {
        if ((int) $userProduct->user_id !== (int) $request->user()->id) {
            return ApiResponse::error('Forbidden.', [], 403);
        }

        try {
            $claim = $service->claim($request->user(), $userProduct);

            return ApiResponse::success([
                'claim' => [
                    'id' => (int) $claim->id,
                    'amount' => ApiFormatter::money($claim->amount),
                    'claimed_at' => ApiFormatter::dateTime($claim->claimed_at),
                ],
                'user_product' => ApiFormatter::userProduct($userProduct->fresh('product')),
            ], __('daily_income.claim_success'), 201);
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 400);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error(__('daily_income.claim_failed'), [], 500);
        }
    }
}
