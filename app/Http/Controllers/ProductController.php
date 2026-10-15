<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Services\ProductCatalogService;
use App\Services\ProductPurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request, ProductCatalogService $productCatalogService): View
    {
        $user = $request->user();

        $catalog = $productCatalogService->groupedAvailableProducts();

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $withdrawableWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
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

        $userProductPurchaseCounts = UserProduct::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'expired'])
            ->selectRaw('product_id, COUNT(*) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($value) => (int) $value)
            ->all();

        return view('products.index', [
            'user' => $user,
            'products' => $catalog['products'],
            'productsByType' => $catalog['productsByType'],
            'productTypeTabs' => $catalog['productTypeTabs'],
            'rechargeBalance' => $rechargeWallet?->balance ?? 0,
            'withdrawableBalance' => $withdrawableWallet?->balance ?? 0,
            'activeUserProducts' => $activeUserProducts,
            'ownedProductIdsForUpgradeBonus' => $ownedProductIdsForUpgradeBonus,
            'userProductPurchaseCounts' => $userProductPurchaseCounts,
            'coupons' => PurchaseCoupon::query()->availableTo($user)->orderByDesc('discount_percent')->get(),
        ]);
    }

    public function purchase(
        Request $request,
        Product $product,
        ProductPurchaseService $productPurchaseService,
    ): RedirectResponse {
        try {
            $data = $request->validate([
                'wallet_type' => ['required', 'string', 'in:recharge,withdrawable'],
                'coupon_id' => ['nullable', 'integer', 'min:1'],
            ]);

            $productPurchaseService->purchase(
                user: $request->user(),
                product: $product,
                walletType: $data['wallet_type'],
                couponId: isset($data['coupon_id']) ? (int) $data['coupon_id'] : null,
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
