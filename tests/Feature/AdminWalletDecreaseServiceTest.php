<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AdminWalletDecreaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class AdminWalletDecreaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_decrease_a_user_wallet_with_an_audit_transaction(): void
    {
        $user = $this->createUser();
        $admin = $this->createAdmin();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_RECHARGE,
            'balance' => 100,
            'locked_balance' => 0,
        ]);

        $transaction = app(AdminWalletDecreaseService::class)->decrease(
            user: $user,
            adminUser: $admin,
            walletType: Wallet::TYPE_RECHARGE,
            amount: 35.50,
            remarks: 'Incorrect credit reversal',
        );

        $this->assertSame('64.50', $wallet->refresh()->balance);
        $this->assertSame('admin_wallet_decrease', $transaction->type);
        $this->assertSame(WalletTransaction::DIRECTION_DEBIT, $transaction->direction);
        $this->assertSame('100.00', $transaction->balance_before);
        $this->assertSame('64.50', $transaction->balance_after);
        $this->assertSame('Incorrect credit reversal', $transaction->remarks);
        $this->assertSame($admin->id, $transaction->meta['admin_user_id']);
    }

    public function test_decrease_is_rejected_when_wallet_balance_is_insufficient(): void
    {
        $user = $this->createUser();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
            'balance' => 10,
            'locked_balance' => 0,
        ]);

        try {
            app(AdminWalletDecreaseService::class)->decrease(
                user: $user,
                adminUser: null,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                amount: 10.01,
                remarks: 'Test deduction',
            );

            $this->fail('Expected an insufficient wallet balance exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Insufficient wallet balance.', $exception->getMessage());
        }

        $this->assertSame('10.00', $wallet->refresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Wallet User',
            'username' => 'wallet-user',
            'phone' => '84912345678',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function createAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'username' => 'wallet-admin',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
