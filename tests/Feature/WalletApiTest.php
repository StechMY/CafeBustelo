<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PDO;
use Tests\TestCase;

class WalletApiTest extends TestCase
{
    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is unavailable.');
        }

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';

        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->artisan('migrate:fresh')->run();
    }

    public function test_wallet_index_returns_screen_ready_filtered_and_page_summaries(): void
    {
        config(['app.currency' => 'RM', 'app.timezone' => 'Asia/Kuala_Lumpur']);

        $user = User::query()->create([
            'name' => 'Wallet User',
            'username' => 'wallet_user',
            'password' => Hash::make('password'),
            'display_wallet_total_credit' => 5000,
            'display_wallet_total_debit' => 900,
        ]);
        $otherUser = User::query()->create([
            'name' => 'Other User',
            'username' => 'other_user',
            'password' => Hash::make('password'),
        ]);

        $rechargeWallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_RECHARGE,
            'balance' => 100,
            'locked_balance' => 5,
        ]);
        $withdrawableWallet = Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
            'balance' => 50,
            'locked_balance' => 0,
        ]);
        $otherWallet = Wallet::query()->create([
            'user_id' => $otherUser->id,
            'type' => Wallet::TYPE_RECHARGE,
            'balance' => 999,
            'locked_balance' => 0,
        ]);

        $this->createTransaction($user, $rechargeWallet, Wallet::TYPE_RECHARGE, WalletTransaction::DIRECTION_CREDIT, 100, 'manual_deposit', '2026-06-01 10:00:00');
        $this->createTransaction($user, $withdrawableWallet, Wallet::TYPE_WITHDRAWABLE, WalletTransaction::DIRECTION_DEBIT, 30, 'product_purchase', '2026-06-02 10:00:00');
        $this->createTransaction($user, $withdrawableWallet, Wallet::TYPE_WITHDRAWABLE, WalletTransaction::DIRECTION_CREDIT, 200, 'commission', '2026-06-20 10:00:00');
        $this->createTransaction($user, $rechargeWallet, Wallet::TYPE_RECHARGE, WalletTransaction::DIRECTION_DEBIT, 50, 'product_purchase', '2026-05-31 10:00:00');
        $this->createTransaction($otherUser, $otherWallet, Wallet::TYPE_RECHARGE, WalletTransaction::DIRECTION_CREDIT, 999, 'manual_deposit', '2026-06-02 10:00:00');

        Sanctum::actingAs($user);

        $pageOne = $this->getJson('/api/v1/wallet?start_date=2026-06-01&end_date=2026-06-02&per_page=1&page=1')
            ->assertOk()
            ->json('data');

        $this->assertSame(300.0, $pageOne['summary']['lifetime']['total_credit']);
        $this->assertSame(5000.0, $pageOne['summary']['lifetime']['display_total_credit']);
        $this->assertTrue($pageOne['summary']['lifetime']['is_total_credit_overridden']);
        $this->assertSame(80.0, $pageOne['summary']['lifetime']['total_debit']);
        $this->assertSame(900.0, $pageOne['summary']['lifetime']['display_total_debit']);
        $this->assertTrue($pageOne['summary']['lifetime']['is_total_debit_overridden']);

        $this->assertSame([
            'total' => 130.0,
            'credit' => 100.0,
            'debit' => 30.0,
            'net' => 70.0,
            'formatted_total' => 'RM 130.00',
            'formatted_credit' => 'RM 100.00',
            'formatted_debit' => 'RM 30.00',
            'formatted_net' => 'RM 70.00',
        ], $pageOne['summary']['all_filtered']);

        $this->assertSame(30.0, $pageOne['summary']['current_page']['total']);
        $this->assertSame(0.0, $pageOne['summary']['current_page']['credit']);
        $this->assertSame(30.0, $pageOne['summary']['current_page']['debit']);
        $this->assertSame(-30.0, $pageOne['summary']['current_page']['net']);
        $this->assertSame(2, $pageOne['meta']['total']);
        $this->assertSame(1, $pageOne['meta']['current_page']);
        $this->assertSame('product_purchase', $pageOne['transactions'][0]['type']);
        $this->assertSame('02 Jun 2026, 10:00 AM', $pageOne['transactions'][0]['created_at_label']);

        $pageTwo = $this->getJson('/api/v1/wallet?start_date=2026-06-01&end_date=2026-06-02&per_page=1&page=2')
            ->assertOk()
            ->json('data');

        $this->assertSame($pageOne['summary']['all_filtered'], $pageTwo['summary']['all_filtered']);
        $this->assertSame(100.0, $pageTwo['summary']['current_page']['total']);
        $this->assertSame(100.0, $pageTwo['summary']['current_page']['credit']);
        $this->assertSame(0.0, $pageTwo['summary']['current_page']['debit']);
        $this->assertSame(100.0, $pageTwo['summary']['current_page']['net']);
        $this->assertSame(2, $pageTwo['meta']['current_page']);
    }

    public function test_wallet_index_validates_filter_values(): void
    {
        $user = User::query()->create([
            'name' => 'Wallet User',
            'username' => 'wallet_user',
            'password' => Hash::make('password'),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/wallet?wallet_type=bad&start_date=2026-06-02&end_date=2026-06-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wallet_type', 'end_date']);
    }

    private function createTransaction(
        User $user,
        Wallet $wallet,
        string $walletType,
        string $direction,
        float $amount,
        string $type,
        string $createdAt,
    ): void {
        WalletTransaction::query()->create([
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'wallet_type' => $walletType,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'remarks' => ucfirst(str_replace('_', ' ', $type)),
            'created_at' => Carbon::parse($createdAt, 'Asia/Kuala_Lumpur'),
            'updated_at' => Carbon::parse($createdAt, 'Asia/Kuala_Lumpur'),
        ]);
    }
}
