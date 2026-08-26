<?php

namespace Tests\Feature;

use App\Exceptions\Qc168GatewayException;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\WithdrawRequests\Pages\ViewWithdrawRequest;
use App\Models\AdminUser;
use App\Models\PaymentGatewayEvent;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168CallbackService;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\Recharge\GatewayRechargeService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PDO;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Qc168OperationalSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $testDatabase = getenv('QC168_TEST_DB_DATABASE');

        if ($testDatabase) {
            putenv('DB_CONNECTION=mysql');
            putenv("DB_DATABASE={$testDatabase}");
            $_ENV['DB_CONNECTION'] = 'mysql';
            $_ENV['DB_DATABASE'] = $testDatabase;
            $_SERVER['DB_CONNECTION'] = 'mysql';
            $_SERVER['DB_DATABASE'] = $testDatabase;
        } else {
            if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                $this->markTestSkipped('SQLite PDO driver is unavailable.');
            }

            putenv('DB_CONNECTION=sqlite');
            putenv('DB_DATABASE=:memory:');
            $_ENV['DB_CONNECTION'] = 'sqlite';
            $_ENV['DB_DATABASE'] = ':memory:';
            $_SERVER['DB_CONNECTION'] = 'sqlite';
            $_SERVER['DB_DATABASE'] = ':memory:';
        }

        parent::setUp();

        config([
            'database.default' => $testDatabase ? 'mysql' : 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.mysql.database' => $testDatabase ?: config('database.connections.mysql.database'),
            'services.qc168.enabled' => true,
            'services.qc168.base_url' => 'https://gateway.example',
            'services.qc168.oc_key' => 'test-key',
            'services.qc168.secret' => 'test-secret',
            'services.qc168.merchant_uid' => 'merchant-123',
            'services.qc168.currency' => 'vnd',
            'services.qc168.country' => 'vn',
            'services.qc168.payment' => 'merchant-channel',
            'services.qc168.deposit_payment' => 'merchant-channel',
            'services.qc168.withdraw_payment' => 'vn02',
        ]);

        $this->artisan('migrate:fresh')->run();

        VipLevel::query()->create([
            'level' => 1,
            'name' => 'VIP 1',
            'required_accumulated_recharge' => 0,
            'required_active_team_size' => 0,
            'min_withdraw_amount' => 1,
            'withdraw_fee_percent' => 2,
            'status' => 'active',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_missing_rows_use_safe_operational_defaults(): void
    {
        $settings = app(Qc168Settings::class);

        $this->assertTrue($settings->depositEnabled());
        $this->assertFalse($settings->withdrawEnabled());
        $this->assertSame(24, $settings->withdrawReleaseHours());
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_authorized_admin_can_toggle_only_three_operational_settings(): void
    {
        $admin = $this->admin('gateway-settings-admin');
        $admin->givePermissionTo($this->permission(Qc168Settings::MANAGE_PERMISSION));
        $this->actingAs($admin, 'admin');

        Livewire::test(Dashboard::class)
            ->assertActionVisible('paymentGatewaySettings')
            ->callAction('paymentGatewaySettings', [
                'deposit_enabled' => false,
                'withdraw_enabled' => true,
                'withdraw_release_hours' => 12,
            ])
            ->assertHasNoActionErrors();

        $this->assertFalse(app(Qc168Settings::class)->depositEnabled());
        $this->assertTrue(app(Qc168Settings::class)->withdrawEnabled());
        $this->assertSame(12, app(Qc168Settings::class)->withdrawReleaseHours());

        Livewire::test(Dashboard::class)
            ->callAction('paymentGatewaySettings', [
                'deposit_enabled' => true,
                'withdraw_enabled' => false,
                'withdraw_release_hours' => 24,
            ])
            ->assertHasNoActionErrors();

        $this->assertSame([
            Qc168Settings::KEY_DEPOSIT_ENABLED,
            Qc168Settings::KEY_WITHDRAW_ENABLED,
            Qc168Settings::KEY_WITHDRAW_RELEASE_HOURS,
        ], Setting::query()
            ->where('key', 'like', 'qc168_%')
            ->orderBy('key')
            ->pluck('key')
            ->all());
        $this->assertSame(2, PaymentGatewayEvent::query()
            ->where('event_type', 'qc168.settings.updated')
            ->where('actor_admin_user_id', $admin->id)
            ->count());

        $event = PaymentGatewayEvent::query()->latest('id')->firstOrFail();
        $this->assertSame('127.0.0.1', $event->payload['ip']);
        $this->assertSame(12, $event->payload['old']['withdraw_release_hours']);
        $this->assertSame(24, $event->payload['new']['withdraw_release_hours']);
    }

    public function test_unauthorized_admin_cannot_see_or_save_gateway_settings(): void
    {
        $admin = $this->admin('unauthorized-settings-admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(Dashboard::class)
            ->assertActionHidden('paymentGatewaySettings');

        try {
            app(Qc168Settings::class)->save([
                'deposit_enabled' => false,
                'withdraw_enabled' => true,
                'withdraw_release_hours' => 12,
            ], $admin, '127.0.0.1');
            $this->fail('An unauthorized admin should not be able to save gateway settings.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('settings', 0);
            $this->assertDatabaseCount('payment_gateway_events', 0);
        }
    }

    public function test_environment_master_switch_overrides_both_admin_toggles(): void
    {
        Setting::setValue(Qc168Settings::KEY_DEPOSIT_ENABLED, true);
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, true);
        config(['services.qc168.enabled' => false]);

        $gateway = app(Qc168Gateway::class);

        $this->assertFalse($gateway->isDepositAvailable());
        $this->assertFalse($gateway->isWithdrawalAvailable());
    }

    public function test_deposit_toggle_blocks_new_orders_without_breaking_existing_callbacks(): void
    {
        $user = $this->user('deposit_toggle');
        Http::fake(function (ClientRequest $request) {
            return Http::response([
                'result' => 'success',
                'data' => [
                    'payment' => [
                        'payment_status' => 'pending',
                        'payment_uid' => 'PAY-TOGGLE',
                        'currency' => 'vnd',
                    ],
                    'payment_url' => 'https://gateway.example/pay/PAY-TOGGLE',
                ],
            ]);
        });

        $recharge = app(GatewayRechargeService::class)->create($user, '1000.00');
        Setting::setValue(Qc168Settings::KEY_DEPOSIT_ENABLED, false);

        $payload = [
            'transaction_type' => 'income',
            'accounting_type' => 'recharge',
            'merchant_uid' => 'merchant-123',
            'amount' => '1000.00',
            'final_amount' => '1000.00',
            'currency' => 'VND',
            'payment' => 'merchant-channel',
            'merchant_order_id' => $recharge->merchant_order_id,
            'payment_status' => 'completed',
            'signture_key' => 'callback-key',
        ];
        $signature = app(Qc168Gateway::class)->sign($payload);
        $outcome = app(Qc168CallbackService::class)->handleCallback($payload, $signature);

        $this->assertTrue($outcome['accepted']);
        $this->assertTrue($recharge->fresh()->isApproved());

        try {
            app(GatewayRechargeService::class)->create($user, '2000.00');
            $this->fail('A disabled deposit toggle should block a new gateway order.');
        } catch (Qc168GatewayException) {
            $this->assertDatabaseCount('recharge_requests', 1);
        }
    }

    public function test_withdrawal_toggle_blocks_filament_and_backend_approval_but_not_rejection(): void
    {
        $withdrawal = $this->pendingWithdrawal('withdraw_toggle');
        $admin = $this->admin('withdraw-review-admin');
        $admin->givePermissionTo([
            $this->permission('ViewAny:WithdrawRequest'),
            $this->permission('View:WithdrawRequest'),
            $this->permission('Audit:Withdraw'),
            $this->permission('ApproveManual:Withdrawal'),
            $this->permission('ApproveScheduledGateway:Withdrawal'),
            $this->permission('ApproveInstantGateway:Withdrawal'),
        ]);
        $this->actingAs($admin, 'admin');

        Livewire::test(ViewWithdrawRequest::class, ['record' => $withdrawal->getRouteKey()])
            ->assertActionEnabled('approve_manual')
            ->assertActionDisabled('approve_gateway_scheduled')
            ->assertActionDisabled('approve_gateway_instant')
            ->assertActionEnabled('reject');

        try {
            app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $admin);
            $this->fail('Scheduled gateway approval should be blocked while automatic withdrawal is off.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdrawal', $exception->errors());
        }

        app(WithdrawService::class)->reject($withdrawal, $admin, 'Rejected while gateway is off.');

        $this->assertSame(WithdrawRequest::STATUS_REJECTED, $withdrawal->fresh()->status);
        $this->assertSame('1000.00', $this->withdrawWallet($withdrawal)->balance);
        $this->assertSame('0.00', $this->withdrawWallet($withdrawal)->locked_balance);
    }

    public function test_delay_is_snapshotted_at_approval_and_only_new_approvals_use_changes(): void
    {
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, true);
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_RELEASE_HOURS, 12);
        Carbon::setTestNow('2026-08-26 10:00:00');
        $first = $this->pendingWithdrawal('delay_first');
        $admin = $this->admin('delay-admin');
        $admin->givePermissionTo($this->permission('ApproveScheduledGateway:Withdrawal'));

        app(WithdrawService::class)->approveWithScheduledGateway($first, $admin);

        $this->assertSame(12, $first->fresh()->gateway_release_delay_hours_snapshot);
        $this->assertSame('2026-08-26 22:00:00', $first->fresh()->gateway_release_at->format('Y-m-d H:i:s'));

        Setting::setValue(Qc168Settings::KEY_WITHDRAW_RELEASE_HOURS, 6);
        $this->assertSame(12, $first->fresh()->gateway_release_delay_hours_snapshot);
        $this->assertSame('2026-08-26 22:00:00', $first->fresh()->gateway_release_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-08-26 11:00:00');
        $second = $this->pendingWithdrawal('delay_second');
        app(WithdrawService::class)->approveWithScheduledGateway($second, $admin);

        $this->assertSame(6, $second->fresh()->gateway_release_delay_hours_snapshot);
        $this->assertSame('2026-08-26 17:00:00', $second->fresh()->gateway_release_at->format('Y-m-d H:i:s'));
    }

    private function pendingWithdrawal(string $username): WithdrawRequest
    {
        $user = $this->user($username);
        $user->forceFill([
            'status' => 'active',
            'withdraw_status' => true,
        ])->save();
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
            'balance' => '1000.00',
            'locked_balance' => '0.00',
        ]);
        UserBankAccount::query()->create([
            'user_id' => $user->id,
            'bank_account_name' => 'Nguyen Hoa',
            'bank_name' => 'Vietcombank',
            'bank_code' => '970436',
            'bank_account_no' => (string) (8000000000 + $user->id),
            'status' => UserBankAccount::STATUS_ACTIVE,
        ]);

        return app(WithdrawService::class)->submit($user, '1000.00');
    }

    private function withdrawWallet(WithdrawRequest $withdrawal): Wallet
    {
        return Wallet::query()
            ->where('user_id', $withdrawal->user_id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->firstOrFail();
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'name' => str_replace('_', ' ', $username),
            'username' => $username,
            'password' => Hash::make('password'),
        ]);
    }

    private function admin(string $username): AdminUser
    {
        return AdminUser::query()->firstOrCreate(
            ['username' => $username],
            ['password' => Hash::make('password'), 'status' => 'active'],
        );
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'admin');
    }
}
