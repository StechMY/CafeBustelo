<?php

namespace App\Services;

use App\Models\AdminTopUp;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AdminTopUpService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function topUp(
        User $user,
        ?AdminUser $adminUser,
        string $walletType,
        float|string $amount,
        bool $countsAsRecharge = false,
        ?string $remarks = null,
    ): AdminTopUp {
        if (! in_array($walletType, [Wallet::TYPE_RECHARGE, Wallet::TYPE_WITHDRAWABLE], true)) {
            throw new RuntimeException('Invalid wallet type.');
        }

        if ((float) $amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user,
            $adminUser,
            $walletType,
            $amount,
            $countsAsRecharge,
            $remarks,
        ): AdminTopUp {
            $topUp = AdminTopUp::query()->create([
                'user_id' => $user->id,
                'admin_user_id' => $adminUser?->id,
                'wallet_type' => $walletType,
                'amount' => $amount,
                'counts_as_recharge' => $countsAsRecharge,
                'status' => 'completed',
                'remarks' => $remarks,
                'meta' => [
                    'created_from' => 'filament_user_resource',
                ],
            ]);

            $this->walletService->credit(
                user: $user,
                walletType: $walletType,
                transactionType: 'admin_top_up',
                amount: $amount,
                reference: $topUp,
                remarks: $remarks ?: 'Admin top-up',
                meta: [
                    'admin_user_id' => $adminUser?->id,
                    'counts_as_recharge' => $countsAsRecharge,
                ],
            );

            if ($walletType === Wallet::TYPE_RECHARGE && $countsAsRecharge) {
                $user->increment('accumulated_recharge', (float) $amount);
            }

            return $topUp;
        });
    }
}