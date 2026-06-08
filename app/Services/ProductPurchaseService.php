<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductPurchaseService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function purchase(User $user, Product $product): UserProduct
    {
        if (!$product->isActive()) {
            throw new RuntimeException(__('products.product_not_available'));
        }

        if (!$user->canOrder()) {
            throw new RuntimeException(__('products.order_disabled'));
        }

        return DB::transaction(function () use ($user, $product): UserProduct {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$lockedProduct->isActive()) {
                throw new RuntimeException(__('products.product_not_available'));
            }

            $purchasedCount = \App\Models\UserProduct::query()
                ->where('user_id', $user->id)
                ->where('product_id', $lockedProduct->id)
                ->whereIn('status', ['active', 'expired', 'completed'])
                ->lockForUpdate()
                ->count();

            $maxPurchase = max(1, (int) ($lockedProduct->max_purchase_per_user ?: 2));

            if ($purchasedCount >= $maxPurchase) {
                throw new RuntimeException(__('products.max_purchase_limit_reached', [
                    'limit' => $maxPurchase,
                ]));
            }

            $now = now();

            $userProduct = UserProduct::query()->create([
                'user_id' => $user->id,
                'product_id' => $lockedProduct->id,
                'price_snapshot' => $lockedProduct->price,
                'daily_income_snapshot' => $lockedProduct->daily_income,
                'duration_days_snapshot' => $lockedProduct->duration_days,
                'claimed_days' => 0,
                'missed_days' => 0,
                'purchased_at' => $now,
                'next_claim_at' => $now->copy()->addDay(),
                'expires_at' => $this->calculateExpiresAt($now, (int) $lockedProduct->duration_days),
                'status' => 'active',
                'meta' => [
                    'product_name_snapshot' => $lockedProduct->name,
                    'product_code_snapshot' => $lockedProduct->code,
                ],
            ]);

            $this->walletService->debit(
                user: $user,
                walletType: Wallet::TYPE_RECHARGE,
                transactionType: 'product_purchase',
                amount: $lockedProduct->price,
                reference: $userProduct,
                remarks: 'Product purchase: ' . $lockedProduct->name,
                meta: [
                    'product_id' => $lockedProduct->id,
                    'product_name' => $lockedProduct->name,
                    'product_code' => $lockedProduct->code,
                ],
            );

            if ($user->status !== 'active') {
                $user->forceFill([
                    'status' => 'active',
                    'activated_at' => $user->activated_at ?? $now,
                ])->save();
            }

            return $userProduct;
        });
    }

    private function calculateExpiresAt(Carbon $purchasedAt, int $durationDays): Carbon
    {
        return $purchasedAt->copy()->addDays($durationDays);
    }
}