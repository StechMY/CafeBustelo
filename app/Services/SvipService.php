<?php

namespace App\Services;

use App\Models\SvipPackage;
use App\Models\SvipPurchase;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SvipService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function purchase(User $user, SvipPackage $package): SvipPurchase
    {
        return DB::transaction(function () use ($user, $package): SvipPurchase {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPackage = SvipPackage::query()
                ->whereKey($package->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPackage->is_active) {
                throw new RuntimeException(__('svip.package_not_available'));
            }

            if ($lockedUser->status !== 'active') {
                throw new RuntimeException(__('svip.account_not_active'));
            }

            $alreadyActive = SvipPurchase::query()
                ->where('user_id', $lockedUser->id)
                ->where('status', SvipPurchase::STATUS_ACTIVE)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if ($alreadyActive) {
                throw new RuntimeException(__('svip.already_active'));
            }

            $price = round((float) $lockedPackage->price, 2);

            if ($price <= 0) {
                throw new RuntimeException(__('svip.invalid_price'));
            }

            $startedAt = now();

            $expiresAt = $lockedPackage->duration_days
                ? $startedAt->copy()->addDays((int) $lockedPackage->duration_days)
                : null;

            $purchase = SvipPurchase::query()->create([
                'user_id' => $lockedUser->id,
                'svip_package_id' => $lockedPackage->id,
                'amount' => $price,
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => SvipPurchase::STATUS_ACTIVE,
                'meta' => [
                    'package_name' => $lockedPackage->name,
                    'duration_days' => $lockedPackage->duration_days,
                ],
            ]);

            $this->walletService->debit(
                user: $lockedUser,
                walletType: Wallet::TYPE_RECHARGE,
                transactionType: 'svip_purchase',
                amount: $price,
                reference: $purchase,
                remarks: 'SVIP purchase',
                meta: [
                    'svip_purchase_id' => $purchase->id,
                    'svip_package_id' => $lockedPackage->id,
                ],
            );

            return $purchase;
        });
    }
}