<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Services\ProductPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $products = Product::query()
            ->where('status', 'active')
            ->orderBy('price')
            ->orderBy('sort_order')
            ->get();

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $activeUserProducts = UserProduct::query()
            ->with('product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('purchased_at')
            ->get();

        $ownedProductIdsForUpgradeBonus = UserProduct::query()
            ->where('user_id', $user->id)
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        return view('products.index', [
            'user' => $user,
            'products' => $products,
            'rechargeBalance' => $rechargeWallet?->balance ?? 0,
            'activeUserProducts' => $activeUserProducts,
            'ownedProductIdsForUpgradeBonus' => $ownedProductIdsForUpgradeBonus,
        ]);
    }

    public function purchase(
        Request $request,
        Product $product,
        ProductPurchaseService $productPurchaseService,
    ): RedirectResponse {
        try {
            $productPurchaseService->purchase(
                user: $request->user(),
                product: $product,
            );

            return redirect()
                ->route('products.index')
                ->with('success', __('products.purchase_success'));
        } catch (RuntimeException $exception) {
            return back()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->with('error', __('products.purchase_failed'));
        }
    }
}