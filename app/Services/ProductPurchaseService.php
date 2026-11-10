<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductPurchaseService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PointService $pointService,
        private readonly InviteDayBonusService $inviteDayBonusService,
        private readonly GoldenEggService $goldenEggService,
        private readonly TreasureChestService $treasureChestService,
        private readonly RedPacketService $redPacketService,
    ) {
    }

    public function purchase(
        User $user,
        Product $product,
        string $walletType = Wallet::TYPE_RECHARGE,
        ?int $couponId = null,
    ): UserProduct {
        if (! $product->isActive()) {
            throw new RuntimeException(__('products.product_not_available'));
        }

        if (! $user->canOrder()) {
            throw new RuntimeException(__('products.order_disabled'));
        }

        if (! in_array($walletType, [
            Wallet::TYPE_RECHARGE,
            Wallet::TYPE_WITHDRAWABLE,
        ], true)) {
            throw new RuntimeException(__('products.invalid_wallet_type'));
        }

        return DB::transaction(function () use ($user, $product, $walletType, $couponId): UserProduct {
            // Serialize this user's purchases across products and payment wallets.
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedProduct->isPurchasableNow()) {
                if (! $lockedProduct->hasDailyStock()) {
                    throw new RuntimeException(__('products.product_sold_out'));
                }

                throw new RuntimeException(__('products.product_not_available_now'));
            }

            if (! $lockedProduct->meetsVipLevelRequirement($user)) {
                throw new RuntimeException(__('products.vip_level_required', [
                    'level' => $lockedProduct->min_vip_level,
                ]));
            }

            $purchasedCount = UserProduct::query()
                ->where('user_id', $user->id)
                ->where('product_id', $lockedProduct->id)
                ->whereIn('status', ['active', 'expired', 'completed'])
                ->lockForUpdate()
                ->count();

            $maxPurchase = $lockedProduct->maxPurchasePerUser();

            if ($purchasedCount >= $maxPurchase) {
                throw new RuntimeException(__('products.max_purchase_limit_reached', [
                    'limit' => $maxPurchase,
                ]));
            }

            $now = now();
            $coupon = null;
            $price = (float) $lockedProduct->price;
            if ($couponId !== null) {
                if (! $lockedProduct->allow_coupons) {
                    throw new RuntimeException(__('golden_egg.product_coupons_disabled'));
                }

                $coupon = PurchaseCoupon::query()->availableTo($user)
                    ->whereKey($couponId)->lockForUpdate()->first();
                if (! $coupon) {
                    throw new RuntimeException(__('golden_egg.invalid_coupon'));
                }
                $price = $coupon->discountedPrice($lockedProduct->price);
            }

            $claimIntervalHours = $lockedProduct->effectiveClaimIntervalHours();
            $totalClaims = $lockedProduct->effectiveTotalClaims();

            $userProduct = UserProduct::query()->create([
                'user_id' => $user->id,
                'product_id' => $lockedProduct->id,
                'price_snapshot' => $price,
                'daily_income_snapshot' => $lockedProduct->daily_income,
                'duration_days_snapshot' => $lockedProduct->duration_days,
                'claim_interval_hours_snapshot' => $claimIntervalHours,
                'total_claims_snapshot' => $totalClaims,
                'product_type_snapshot' => $lockedProduct->product_type,
                'extra_points_snapshot' => (int) $lockedProduct->extra_points,
                'claimed_days' => 0,
                'missed_days' => 0,
                'purchased_at' => $now,
                'next_claim_at' => $now->copy()->addHours($claimIntervalHours),
                'expires_at' => UserProduct::calculateExpiresAt($now, $claimIntervalHours, $totalClaims),
                'status' => 'active',
                'meta' => [
                    'product_name_snapshot' => $lockedProduct->name,
                    'product_code_snapshot' => $lockedProduct->code,
                    'payment_wallet_type' => $walletType,
                    'original_price' => $lockedProduct->price,
                    'coupon_id' => $coupon?->id,
                    'discount_percent' => $coupon?->discount_percent,
                    'discount_amount' => round((float) $lockedProduct->price - $price, 2),
                ],
            ]);

            if ($price > 0 || $coupon === null) {
                $this->walletService->debit(
                    user: $user,
                    walletType: $walletType,
                    transactionType: 'product_purchase',
                    amount: $price,
                    reference: $userProduct,
                    remarks: 'Product purchase: ' . $lockedProduct->name,
                    meta: [
                        'product_id' => $lockedProduct->id,
                        'product_name' => $lockedProduct->name,
                        'product_code' => $lockedProduct->code,
                        'payment_wallet_type' => $walletType,
                        'claim_interval_hours' => $claimIntervalHours,
                        'total_claims' => $totalClaims,
                        'product_type' => $lockedProduct->product_type,
                        'coupon_id' => $coupon?->id,
                        'discount_percent' => $coupon?->discount_percent,
                    ],
                );
            }

            $coupon?->update(['used_at' => $now, 'user_product_id' => $userProduct->id]);

            if ($lockedProduct->stock_quantity !== null) {
                $lockedProduct->increment('sold_quantity');
            }

            $this->pointService->giveUpgradeDayPurchasePoints($user, $userProduct);
            $this->inviteDayBonusService->handlePurchase($user, $userProduct);
            // Temporarily disabled: first-purchase points for the direct inviter.
            // $this->pointService->giveInviteDayFirstPurchasePoints($user, $userProduct);

            if ($user->status !== 'active') {
                $user->forceFill([
                    'status' => 'active',
                    'activated_at' => $user->activated_at ?? $now,
                ])->save();
            }

            $this->goldenEggService->qualifyFirstPurchase($user, $userProduct);
            $this->goldenEggService->givePurchaseChance($user, $userProduct);
            $this->treasureChestService->givePurchaseKeys($user, $userProduct);
            $this->redPacketService->handlePurchase($user, $userProduct);

            return $userProduct;
        }, 3);
    }
}
