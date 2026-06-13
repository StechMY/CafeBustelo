<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WelcomeBonusService
{
    public const SETTING_ENABLED = 'welcome_bonus_enabled';

    public const SETTING_AMOUNT = 'welcome_bonus_amount';

    public const TRANSACTION_TYPE = 'welcome_bonus';

    public function isEnabled(): bool
    {
        return Setting::bool(self::SETTING_ENABLED, false);
    }

    public function amount(): float
    {
        return Setting::decimal(self::SETTING_AMOUNT, 70000);
    }

    public function give(User $user): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $amount = $this->amount();

        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount): void {
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyGiven = WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', self::TRANSACTION_TYPE)
                ->exists();

            if ($alreadyGiven) {
                return;
            }

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_WITHDRAWABLE)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (float) $wallet->balance;
            $after = round($before + $amount, 2);

            $wallet->update([
                'balance' => $after,
            ]);

            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => Wallet::TYPE_WITHDRAWABLE,
                'direction' => WalletTransaction::DIRECTION_CREDIT,
                'type' => self::TRANSACTION_TYPE,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'remarks' => __('wallet.types.welcome_bonus'),
            ]);
        });
    }
}