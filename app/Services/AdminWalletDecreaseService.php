<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use RuntimeException;

class AdminWalletDecreaseService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function decrease(
        User $user,
        ?AdminUser $adminUser,
        string $walletType,
        float|string $amount,
        ?string $remarks = null,
    ): WalletTransaction {
        if (! in_array($walletType, [Wallet::TYPE_RECHARGE, Wallet::TYPE_WITHDRAWABLE], true)) {
            throw new RuntimeException('Invalid wallet type.');
        }

        if ((float) $amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        return $this->walletService->debit(
            user: $user,
            walletType: $walletType,
            transactionType: 'admin_wallet_decrease',
            amount: $amount,
            remarks: $remarks ?: 'Admin wallet decrease',
            meta: [
                'admin_user_id' => $adminUser?->id,
                'created_from' => 'filament_user_resource',
            ],
        );
    }
}
