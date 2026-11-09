<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\AdminUser;
use App\Models\RechargeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use App\Services\Recharge\YdPayRechargeService;
use App\Services\Settings\YdPaySettings;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class YdPayGatewayTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = ':memory:';
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'services.ydpay.enabled' => true, 'services.ydpay.deposit_enabled' => true,
            'services.ydpay.withdraw_enabled' => true, 'services.ydpay.secret' => 'DOC_DEMO_KEY_ONLY',
            'services.ydpay.merchant_id' => '502002001', 'services.ydpay.base_url' => 'https://gateway.example',
            'services.ydpay.deposit_gateway_id' => '19', 'services.ydpay.withdraw_gateway_id' => '22',
            'services.ydpay.callback_ips' => ['127.0.0.1'], 'app.url' => 'https://merchant.example',
            'third_party.outbound_proxy' => 'http://proxy.example:3128']);
        $this->artisan('migrate:fresh')->run();
        Http::preventStrayRequests();
    }

    public function test_documented_signature_vector_and_exact_minor_units(): void
    {
        $gateway = app(YdPayGateway::class);
        $this->assertSame('5D6A79908798F7E6D3DEB5EB92D3CA16', $gateway->sign([
            'mchId' => '100000000', 'reqTime' => '20261006120000', 'version' => '1.0',
        ]));
        $this->assertSame('27900000', $gateway->minorAmount('279000.00'));
        $this->assertSame('5000001', $gateway->minorAmount('50000.01'));
        $payload = $this->signed(['zero' => '0', 'boolean' => false, 'spaces' => ' x ', 'empty' => '']);
        $this->assertTrue($gateway->verify($payload));
        $payload['spaces'] = 'x';
        $this->assertFalse($gateway->verify($payload));
    }

    public function test_deposit_uses_form_payload_and_waits_for_verified_callback(): void
    {
        Http::fake(['gateway.example/*' => Http::response($this->signed([
            'retCode' => '0', 'payOrderId' => '123456789012345', 'payJumpUrl' => 'https://cashier.example/pay',
        ]))]);
        $order = app(YdPayRechargeService::class)->create($this->user(), '50000.01', '127.0.0.1');
        Http::assertSent(fn ($request) => $request['amount'] === '5000001' && $request['currency'] === 'VND'
            && $request['gatewayId'] === '19' && preg_match('/^[0-9]{1,30}$/', $request['mchOrderNo'])
            && $request['notifyUrl'] === 'https://merchant.example/payment-gateway/ydpay/deposit/callback'
            && str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded'));
        $this->assertSame('pending', $order->status);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_missing_or_invalid_proxy_blocks_approval_and_outbound_requests(): void
    {
        [$request] = $this->payout(false);
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        foreach ([null, '', 'not-a-url', 'ftp://proxy.example:3128', "http://proxy.example:3128\r\n", 'http://proxy.example:0',
            'http://proxy.example:3128/path', 'http://proxy.example:3128?direct=true', 'http://proxy.example:3128#fragment'] as $proxy) {
            config(['third_party.outbound_proxy' => $proxy]);
            $this->assertFalse(app(YdPayGateway::class)->isAvailable());
            try {
                app(YdPayWithdrawalService::class)->approve($request, $admin);
                $this->fail('Invalid proxy must block approval.');
            } catch (\RuntimeException) {
                $this->assertSame('pending', $request->fresh()->status);
            }
            try {
                app(YdPayGateway::class)->query('123456789', 'withdraw');
                $this->fail('Invalid proxy must block outbound HTTP.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('YDPAY requires a valid THIRD_PARTY_OUTBOUND_PROXY URL.', $exception->getMessage());
            }
        }
        $this->assertSame(0, $request->payoutAttempts()->count());
        Http::assertNothingSent();
    }

    public function test_transport_uses_proxy_for_both_businesses_and_never_retries_directly(): void
    {
        $proxy = 'http://synthetic-user:synthetic-password@proxy.example:3128';
        config(['third_party.outbound_proxy' => $proxy]);
        $calls = [];
        Http::fake(function ($request, $options) use ($proxy, &$calls) {
            $calls[] = $options;
            throw new ConnectionException('Synthetic failure at '.$proxy);
        });
        foreach (['deposit', 'withdraw'] as $business) {
            try {
                app(YdPayGateway::class)->query('123456789', $business);
                $this->fail('Proxy failure must be reported.');
            } catch (\RuntimeException $exception) {
                $this->assertSame('YDPAY transport failed; query the original order through the configured proxy.', $exception->getMessage());
                $this->assertNull($exception->getPrevious());
            }
        }
        $this->assertCount(2, $calls);
        foreach ($calls as $options) {
            $this->assertSame($proxy, $options['proxy']);
            $this->assertTrue($options['verify']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertFalse($options['http_errors']);
        }
    }

    public function test_deposit_callback_query_preserves_values_and_credits_only_once(): void
    {
        $order = $this->deposit();
        $payload = $this->signed(['mchId' => '502002001', 'gatewayId' => '19', 'mchOrderNo' => $order->merchant_order_id,
            'payOrderId' => '123456789012345', 'amount' => '5000000', 'income' => '5000000',
            'status' => '2', 'backType' => '2', 'reqTime' => '20261006120000', 'extraField' => ' preserve spaces ']);
        $failed = $this->signed([...$payload, 'status' => '-1', 'income' => '0']);
        $this->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($failed))->assertOk()->assertContent('success');
        $this->assertSame('failed', $order->fresh()->gateway_payment_status);
        $this->assertSame(0, WalletTransaction::query()->count());
        $url = '/payment-gateway/ydpay/deposit/callback?'.http_build_query($payload);
        $this->post($url)->assertOk()->assertContent('success');
        $this->post($url)->assertOk()->assertContent('success');
        $this->assertSame('approved', $order->fresh()->status);
        $this->assertSame('50000.00', Wallet::query()->where('type', Wallet::TYPE_RECHARGE)->firstOrFail()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_wrong_amount_signature_ip_and_browser_return_cannot_credit(): void
    {
        $order = $this->deposit();
        $payload = ['mchId' => '502002001', 'gatewayId' => '19', 'mchOrderNo' => $order->merchant_order_id,
            'payOrderId' => '123456789012345', 'amount' => '5000000', 'status' => '2', 'backType' => '2'];
        foreach ([['amount' => '50000'], ['income' => '0'], ['backType' => '1'], ['mchId' => 'other'], ['gatewayId' => '20'], ['sandbox' => '1']] as $invalid) {
            $this->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($this->signed(array_merge($payload, $invalid))))->assertStatus(422);
        }
        $signed = $this->signed($payload);
        $signed['sign'] = str_repeat('0', 32);
        $this->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($signed))->assertStatus(422);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])
            ->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($this->signed($payload)))->assertStatus(422);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_payout_consumes_full_reserve_once_without_second_debit(): void
    {
        [$request, $attempt] = $this->payout();
        $payload = $this->payoutPayload($attempt->merchant_order_id, '2');
        $url = '/payment-gateway/ydpay/withdraw/callback?'.http_build_query($payload);
        $this->post($url)->assertOk()->assertContent('success');
        $this->post($url)->assertOk()->assertContent('success');
        $this->assertSame('completed', $request->fresh()->payout_status);
        $wallet = Wallet::query()->where('type', Wallet::TYPE_WITHDRAWABLE)->firstOrFail();
        $this->assertSame('18455.00', $wallet->balance);
        $this->assertSame('120000.00', $wallet->locked_balance);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_cancelled_failure_stays_cancelled_and_late_success_requires_review(): void
    {
        [$request, $attempt] = $this->payout();
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        $failure = $this->signed([...$this->payoutPayload($attempt->merchant_order_id, '3'), 'retCode' => '0']);
        Http::fake(['gateway.example/*' => Http::response($failure)]);
        // Exercise the same provider routing used by the existing Filament Cancel action.
        $router = app(Qc168WithdrawalPayoutService::class);
        $router->cancel($request, $admin, 'Verified YDPAY failure');
        $wallet = Wallet::query()->where('type', Wallet::TYPE_WITHDRAWABLE)->firstOrFail();
        $this->assertSame('318455.00', $wallet->balance);
        $this->assertSame('120000.00', $wallet->locked_balance);
        $service = app(YdPayWithdrawalService::class);
        $service->process($this->payoutPayload($attempt->merchant_order_id, '3'), 'callback');
        $this->assertSame('cancelled', $request->fresh()->payout_status);
        $this->assertFalse($request->fresh()->canCancelPayout());
        try {
            $router->cancel($request->fresh(), $admin, 'Duplicate cancellation');
            $this->fail('Second cancellation must not refund.');
        } catch (\RuntimeException) {
            $this->assertSame('318455.00', $wallet->fresh()->balance);
            $this->assertSame('120000.00', $wallet->fresh()->locked_balance);
        }
        $service->process($this->payoutPayload($attempt->merchant_order_id, '2'), 'callback');
        $this->assertSame('refund_review', $request->fresh()->payout_status);
        $this->assertSame('completed', $request->fresh()->gateway_payment_status);
        $this->assertSame('provider_completed_after_cancel', $request->fresh()->gateway_meta['financial_review_reason']);
        $this->assertSame('120000.00', $wallet->fresh()->locked_balance);
        $this->assertSame(1, WalletTransaction::query()->where('type', 'withdraw_gateway_cancelled')->count());
    }

    public function test_retry_preserves_old_attempt_and_late_old_success_cannot_consume_reserve(): void
    {
        [$request, $attempt] = $this->payout();
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        Http::fake(function ($http) use ($attempt) {
            if (str_ends_with($http->url(), '/api/agentpay/apply')) {
                return Http::response($this->signed(['retCode' => '0', 'agentpayOrderId' => '123456789012347', 'gatewayId' => 22, 'status' => 1]));
            }
            $original = $http['mchOrderNo'] === $attempt->merchant_order_id;

            return Http::response($this->signed(['retCode' => '0', 'agentpayOrderId' => $original ? '123456789012346' : '123456789012347',
                'mchOrderNo' => $http['mchOrderNo'], 'amount' => '27900000', 'gatewayId' => '22', 'status' => $original ? '3' : '1']));
        });
        $newAttempt = app(Qc168WithdrawalPayoutService::class)->retry($request, $admin, 'Retry verified final failure');
        $this->assertNotSame($attempt->merchant_order_id, $newAttempt->merchant_order_id);
        $this->assertSame(2, $request->payoutAttempts()->count());
        $this->assertSame('failed', $attempt->fresh()->gateway_status);
        $this->assertSame('processing', $request->fresh()->payout_status);
        app(Qc168WithdrawalPayoutService::class)->sync($request->fresh(), 'filament', $admin);
        $this->assertDatabaseHas('payment_gateway_events', ['event_type' => 'withdrawal.gateway_sync',
            'provider' => 'ydpay', 'actor_admin_user_id' => $admin->id, 'source' => 'filament']);
        app(YdPayWithdrawalService::class)->process($this->payoutPayload($attempt->merchant_order_id, '2'), 'callback');
        $this->assertSame('refund_review', $request->fresh()->payout_status);
        $this->assertSame('420000.00', Wallet::query()->firstOrFail()->locked_balance);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_ambiguous_payout_submission_is_not_resent(): void
    {
        [$request, $attempt] = $this->payout();
        $this->assertNull(app(YdPayWithdrawalService::class)->dispatch($request));
        $this->assertSame(1, $request->payoutAttempts()->count());
        Http::assertNothingSent();
    }

    public function test_approval_snapshots_delay_and_dispatch_sends_net_amount_and_bank_snapshot(): void
    {
        [$request] = $this->payout(false);
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        $service = app(YdPayWithdrawalService::class);
        $service->approve($request, $admin);
        $request->refresh();
        $this->assertEquals(24, $request->reviewed_at->diffInHours($request->gateway_release_at));
        Setting::setValue('ydpay_withdraw_release_hours', 48);
        $this->assertEquals(24, $request->fresh()->gateway_release_delay_hours_snapshot);
        $this->assertNull($service->dispatch($request));
        Http::assertNothingSent();
        $this->travel(25)->hours();
        Http::fake(function ($http) {
            if (str_ends_with($http->url(), '/api/agentpay/apply')) {
                $this->assertSame('27900000', $http['amount']);
                $this->assertSame('44', $http['bankCode']);
                $this->assertSame('00012345678', $http['accountNo']);
                $this->assertArrayNotHasKey('currency', $http->data());

                return Http::response($this->signed(['retCode' => '0', 'agentpayOrderId' => '123456789012346', 'gatewayId' => 22, 'status' => 1]));
            }

            return Http::response($this->signed(['retCode' => '0', 'agentpayOrderId' => '123456789012346',
                'mchOrderNo' => $http['mchOrderNo'], 'amount' => '27900000', 'gatewayId' => '22', 'status' => '2']));
        });
        $service->dispatch($request);
        $this->assertSame('completed', $request->fresh()->payout_status);
        $this->assertSame('120000.00', Wallet::query()->firstOrFail()->locked_balance);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_dashboard_settings_require_permission_and_control_gateway_availability(): void
    {
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        $settings = app(YdPaySettings::class);
        try {
            $settings->save(['deposit_enabled' => false, 'withdraw_enabled' => false, 'withdraw_release_hours' => 48], $admin);
            $this->fail('Unauthorized settings must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $admin->givePermissionTo(Permission::findOrCreate('ManagePaymentGateway:Settings', 'admin'));
        $settings->save(['deposit_enabled' => false, 'withdraw_enabled' => false, 'withdraw_release_hours' => 48], $admin);
        $this->assertFalse(app(YdPayGateway::class)->isAvailable());
        $this->assertFalse(app(YdPayGateway::class)->isAvailable('withdraw'));
        $this->assertSame(48, $settings->releaseHours());
        $this->assertDatabaseHas('payment_gateway_events', ['provider' => 'ydpay', 'event_type' => 'ydpay.settings.updated', 'actor_admin_user_id' => $admin->id]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Livewire::test(Dashboard::class)
            ->assertActionVisible('ydpaySettings')
            ->callAction('ydpaySettings', ['deposit_enabled' => true, 'withdraw_enabled' => false, 'withdraw_release_hours' => 12])
            ->assertHasNoActionErrors();
        $this->assertTrue(app(YdPayGateway::class)->isAvailable());
        $this->assertSame(12, $settings->releaseHours());
    }

    public function test_disabled_withdrawal_blocks_approval_in_backend(): void
    {
        [$request] = $this->payout(false);
        Setting::setValue('ydpay_withdraw_enabled', false);
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        try {
            app(YdPayWithdrawalService::class)->approve($request, $admin);
            $this->fail('Disabled withdrawals must not be approved.');
        } catch (\RuntimeException) {
            $this->assertSame('pending', $request->fresh()->status);
            $this->assertSame(0, $request->payoutAttempts()->count());
        }
        Http::assertNothingSent();
    }

    private function signed(array $payload): array
    {
        return [...$payload, 'sign' => app(YdPayGateway::class)->sign($payload)];
    }

    private function user(): User
    {
        return User::query()->create(['name' => 'YDPAY User', 'username' => 'ydpay_user', 'password' => bcrypt('password'), 'status' => 'active']);
    }

    private function deposit(): RechargeRequest
    {
        return RechargeRequest::query()->create(['user_id' => $this->user()->id, 'amount' => '50000.00',
            'deposit_method' => 'gateway', 'gateway_provider' => 'ydpay', 'merchant_order_id' => '20261109001',
            'gateway_channel' => '19', 'gateway_currency' => 'vnd', 'status' => 'pending']);
    }

    private function payout(bool $withAttempt = true): array
    {
        $user = $this->user();
        Wallet::query()->create(['user_id' => $user->id, 'type' => Wallet::TYPE_WITHDRAWABLE, 'balance' => '18455.00', 'locked_balance' => '420000.00']);
        $request = WithdrawRequest::query()->create(['user_id' => $user->id, 'amount' => '300000.00', 'net_amount' => '279000.00',
            'fee_amount' => '21000.00', 'status' => $withAttempt ? 'approved' : 'pending', 'approval_mode' => $withAttempt ? 'gateway_scheduled' : null,
            'gateway_provider' => 'ydpay', 'payout_status' => $withAttempt ? 'submitted' : null, 'gateway_release_at' => now(),
            'gateway_current_merchant_order_id' => $withAttempt ? '20261109002' : null, 'bank_name' => 'Vietcombank', 'bank_code' => '970436',
            'bank_account_name' => 'Test User', 'bank_account_no' => '00012345678', 'vip_level_snapshot' => 1,
            'min_withdraw_amount_snapshot' => 1, 'withdraw_fee_percent_snapshot' => 7]);
        if (! $withAttempt) {
            return [$request, null];
        }
        $attempt = $request->payoutAttempts()->create(['provider' => 'ydpay', 'attempt_no' => 1, 'merchant_order_id' => '20261109002',
            'amount' => '279000.00', 'currency' => 'vnd', 'payment_channel' => '22', 'bank_name_snapshot' => 'Vietcombank',
            'bank_code_snapshot' => '44', 'bank_account_name_snapshot' => 'Test User', 'bank_account_no_snapshot' => '00012345678',
            'gateway_status' => 'create_pending']);

        return [$request, $attempt];
    }

    private function payoutPayload(string $merchantOrderId, string $status): array
    {
        return $this->signed(['agentpayOrderId' => '123456789012346', 'mchOrderNo' => $merchantOrderId,
            'gatewayId' => '22', 'amount' => '27900000', 'fee' => '390600', 'status' => $status]);
    }
}
