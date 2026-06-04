<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function getOrCreateWallet(User $user, string $type): Wallet
    {
        return Wallet::query()->firstOrCreate([
            'user_id' => $user->id,
            'type' => $type,
        ], [
            'balance' => 0,
            'locked_balance' => 0,
        ]);
    }

    public function credit(
        User $user,
        string $walletType,
        string $transactionType,
        float|string $amount,
        ?Model $reference = null,
        ?string $remarks = null,
        array $meta = [],
    ): WalletTransaction {
        if ((float) $amount <= 0) {
            throw new RuntimeException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $walletType, $transactionType, $amount, $reference, $remarks, $meta, ): WalletTransaction {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = Wallet::query()->create([
                    'user_id' => $user->id,
                    'type' => $walletType,
                    'balance' => 0,
                    'locked_balance' => 0,
                ]);

                $wallet = Wallet::query()
                    ->whereKey($wallet->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + (float) $amount;

            $wallet->forceFill([
                'balance' => $balanceAfter,
            ])->save();

            return WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => $walletType,
                'type' => $transactionType,
                'direction' => WalletTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'remarks' => $remarks,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function debit(
        User $user,
        string $walletType,
        string $transactionType,
        float|string $amount,
        ?Model $reference = null,
        ?string $remarks = null,
        array $meta = [],
    ): WalletTransaction {
        if ((float) $amount <= 0) {
            throw new RuntimeException('Debit amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $walletType, $transactionType, $amount, $reference, $remarks, $meta, ): WalletTransaction {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new RuntimeException('Wallet not found.');
            }

            $balanceBefore = (float) $wallet->balance;

            if ($balanceBefore < (float) $amount) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $balanceAfter = $balanceBefore - (float) $amount;

            $wallet->forceFill([
                'balance' => $balanceAfter,
            ])->save();

            return WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => $walletType,
                'type' => $transactionType,
                'direction' => WalletTransaction::DIRECTION_DEBIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'remarks' => $remarks,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function lock(
        \App\Models\User $user,
        string $walletType,
        string $transactionType,
        float|string $amount,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        ?string $remarks = null,
        array $meta = [],
    ): \App\Models\WalletTransaction {
        if ((float) $amount <= 0) {
            throw new \RuntimeException('Lock amount must be greater than zero.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $walletType, $transactionType, $amount, $reference, $remarks, $meta, ): \App\Models\WalletTransaction {
            $wallet = \App\Models\Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Wallet not found.');
            }

            $balanceBefore = (float) $wallet->balance;

            if ($balanceBefore < (float) $amount) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $balanceAfter = $balanceBefore - (float) $amount;

            $wallet->forceFill([
                'balance' => $balanceAfter,
                'locked_balance' => (float) $wallet->locked_balance + (float) $amount,
            ])->save();

            return \App\Models\WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => $walletType,
                'type' => $transactionType,
                'direction' => \App\Models\WalletTransaction::DIRECTION_DEBIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'remarks' => $remarks,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function releaseLocked(
        \App\Models\User $user,
        string $walletType,
        string $transactionType,
        float|string $amount,
        ?\Illuminate\Database\Eloquent\Model $reference = null,
        ?string $remarks = null,
        array $meta = [],
    ): \App\Models\WalletTransaction {
        if ((float) $amount <= 0) {
            throw new \RuntimeException('Release amount must be greater than zero.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $walletType, $transactionType, $amount, $reference, $remarks, $meta, ): \App\Models\WalletTransaction {
            $wallet = \App\Models\Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Wallet not found.');
            }

            if ((float) $wallet->locked_balance < (float) $amount) {
                throw new \RuntimeException('Insufficient locked balance.');
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + (float) $amount;

            $wallet->forceFill([
                'balance' => $balanceAfter,
                'locked_balance' => (float) $wallet->locked_balance - (float) $amount,
            ])->save();

            return \App\Models\WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => $walletType,
                'type' => $transactionType,
                'direction' => \App\Models\WalletTransaction::DIRECTION_CREDIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'remarks' => $remarks,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function consumeLocked(
        \App\Models\User $user,
        string $walletType,
        float|string $amount,
    ): void {
        if ((float) $amount <= 0) {
            throw new \RuntimeException('Consume amount must be greater than zero.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $walletType, $amount): void {
            $wallet = \App\Models\Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                throw new \RuntimeException('Wallet not found.');
            }

            if ((float) $wallet->locked_balance < (float) $amount) {
                throw new \RuntimeException('Insufficient locked balance.');
            }

            $wallet->forceFill([
                'locked_balance' => (float) $wallet->locked_balance - (float) $amount,
            ])->save();
        });
    }
}