<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\RechargeRequests\Pages\ListRechargeRequests;
use App\Filament\Resources\RechargeRequests\Pages\ViewRechargeRequest;
use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use App\Filament\Resources\WithdrawRequests\Pages\ViewWithdrawRequest;
use App\Http\Controllers\Api\RechargeController;
use App\Models\AdminUser;
use App\Models\PaymentGatewayEvent;
use App\Models\RechargeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\Qc168CallbackService;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\PaymentGateway\YdPayGateway;
use App\Services\PaymentGateway\YdPayWithdrawalService;
use App\Services\Recharge\YdPayRechargeService;
use App\Services\RechargeApprovalService;
use App\Services\Settings\YdPaySettings;
use App\Support\Api\ApiFormatter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->freezeTime();
        Http::fake(['gateway.example/*' => Http::response($this->signed([
            'retCode' => '0', 'payOrderId' => '123456789012345', 'payJumpUrl' => 'https://cashier.example/pay',
        ]))]);
        $order = app(YdPayRechargeService::class)->create($this->user(), '50000.01', '127.0.0.1');
        Http::assertSent(fn ($request) => $request['amount'] === '5000001' && $request['currency'] === 'VND'
            && $request['gatewayId'] === '19' && preg_match('/^[0-9]{1,30}$/', $request['mchOrderNo'])
            && $request['notifyUrl'] === 'https://merchant.example/payment-gateway/ydpay/deposit/callback'
            && str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded'));
        $this->assertSame('pending', $order->status);
        $this->assertSame(now()->addMinutes(60)->toAtomString(), $order->gateway_meta['deposit_expires_at']);
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->actingAs($order->user)->withSession(['_old_input' => ['provider' => 'qc168']])
            ->get(route('recharge.create'))->assertOk()->assertViewHas('gatewayAvailable', false)
            ->assertSee('value="ydpay"', false)->assertDontSee('value="qc168"', false);
        $apiRequest = Request::create('/api/v1/recharge');
        $apiRequest->setUserResolver(fn () => $order->user);
        $response = app(RechargeController::class)->index($apiRequest);
        $this->assertSame(['ydpay'], collect($response->getData(true)['data']['deposit_methods'])->pluck('provider')->filter()->values()->all());
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
            'payOrderId' => '123456789012345', 'amount' => '5000000', 'income' => '5000000', 'status' => '2', 'backType' => '2'];
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
        $this->assertSame(1, PaymentGatewayEvent::query()->where('event_type', 'withdrawal.gateway_completed')->count());
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
            $settings->save(['deposit_enabled' => false, 'withdraw_enabled' => false, 'withdraw_release_hours' => 48, 'deposit_expiry_minutes' => 90], $admin);
            $this->fail('Unauthorized settings must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $admin->givePermissionTo(Permission::findOrCreate('ManagePaymentGateway:Settings', 'admin'));
        $this->assertSame(60, $settings->depositExpiryMinutes());
        $settings->save(['deposit_enabled' => false, 'withdraw_enabled' => false, 'withdraw_release_hours' => 48, 'deposit_expiry_minutes' => 90], $admin);
        $this->assertFalse(app(YdPayGateway::class)->isAvailable());
        $this->assertFalse(app(YdPayGateway::class)->isAvailable('withdraw'));
        $this->assertSame(48, $settings->releaseHours());
        $this->assertSame(90, $settings->depositExpiryMinutes());
        $this->assertDatabaseHas('payment_gateway_events', ['provider' => 'ydpay', 'event_type' => 'ydpay.settings.updated', 'actor_admin_user_id' => $admin->id]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Livewire::test(Dashboard::class)
            ->assertActionVisible('ydpaySettings')
            ->assertActionHidden('paymentGatewaySettings')
            ->callAction('ydpaySettings', ['deposit_enabled' => true, 'withdraw_enabled' => false, 'withdraw_release_hours' => 12, 'deposit_expiry_minutes' => 60])
            ->assertHasNoActionErrors();
        $this->assertTrue(app(YdPayGateway::class)->isAvailable());
        $this->assertSame(12, $settings->releaseHours());
        $this->assertSame(60, $settings->depositExpiryMinutes());
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

    #[DataProvider('depositStates')]
    public function test_every_deposit_query_status_has_ydpay_semantics(string $status, string $state, bool $paid): void
    {
        $order = $this->deposit();
        $result = app(YdPayRechargeService::class)->process($this->depositPayload($order, $status), 'sync');
        $this->assertSame($state, $result);
        $this->assertSame($state, $order->fresh()->gateway_payment_status);
        $this->assertSame($status, $order->fresh()->gateway_meta['ydpay_status']);
        $this->assertSame($paid, $order->fresh()->isPaymentConfirmed());
        $this->assertSame($paid ? 1 : 0, WalletTransaction::query()->count());
        $this->assertSame($status === '4', $order->fresh()->requiresGatewayReview());
    }

    public static function depositStates(): array
    {
        return [['0', 'processing', false], ['1', 'pending', false], ['2', 'completed', true],
            ['3', 'completed', true], ['4', 'refund_review', false], ['-1', 'failed', false], ['-2', 'closed', false]];
    }

    public function test_deposit_paid_query_and_callback_duplicates_cannot_double_credit_or_regress(): void
    {
        $order = $this->deposit();
        $service = app(YdPayRechargeService::class);
        foreach (['0', '1', '2', '3', '-1', '-2', '1'] as $status) {
            $service->process($this->depositPayload($order, $status), 'sync');
        }
        $this->assertSame('completed', $order->fresh()->gateway_payment_status);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame('50000.00', Wallet::query()->sole()->balance);
    }

    #[DataProvider('refundHistories')]
    public function test_refund_requires_review_without_automatic_credit_or_clawback(bool $paidFirst): void
    {
        $order = $this->deposit();
        $service = app(YdPayRechargeService::class);
        if ($paidFirst) {
            $service->process($this->depositPayload($order, '2'), 'sync');
        }
        $service->process($this->depositPayload($order, '4'), 'sync');
        $service->process($this->depositPayload($order, '2'), 'sync');
        $this->assertSame('refund_review', $order->fresh()->gateway_payment_status);
        $this->assertFalse($order->fresh()->isPaymentConfirmed());
        $this->assertSame($paidFirst ? 1 : 0, WalletTransaction::query()->count());
        $this->actingAs($order->user)->getJson(route('recharge.gateway.status', $order))
            ->assertOk()->assertJsonPath('payment_confirmed', false)->assertJsonPath('requires_review', true);
    }

    public static function refundHistories(): array
    {
        return [[true], [false]];
    }

    public function test_closed_deposit_cannot_regress_to_unpaid_processing_and_manual_approval_is_blocked(): void
    {
        $order = $this->deposit();
        $service = app(YdPayRechargeService::class);
        foreach (['-2', '0', '1'] as $state) {
            $service->process($this->depositPayload($order, $state), 'sync');
        }
        $this->assertSame('closed', $order->fresh()->gateway_payment_status);
        try {
            app(RechargeApprovalService::class)->approve($order, null);
            $this->fail('An unpaid YDPAY deposit cannot be approved locally.');
        } catch (\RuntimeException) {
            $this->assertSame('pending', $order->fresh()->status);
            $this->assertSame(0, WalletTransaction::query()->count());
        }
    }

    public function test_callback_requires_paid_income_and_rejects_query_only_statuses(): void
    {
        $order = $this->deposit();
        $base = [...$this->depositPayload($order, '2'), 'backType' => '2'];
        foreach ([[], ['income' => '4999999'], ['status' => '3', 'income' => '5000000'],
            ['status' => '4'], ['status' => '0'], ['status' => 'completed']] as $changes) {
            $this->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($this->signed([...$base, ...$changes])))
                ->assertStatus(422);
        }
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_query_requires_currency_success_code_and_original_requested_identity(): void
    {
        $order = $this->deposit();
        $base = $this->depositPayload($order, '2');
        foreach ([['currency' => null], ['currency' => 'USD'], ['retCode' => null], ['retCode' => '9999']] as $changes) {
            try {
                app(YdPayRechargeService::class)->process($this->signed([...$base, ...$changes]), 'sync');
                $this->fail('Mismatched query evidence must be rejected.');
            } catch (\RuntimeException) {
                $this->assertSame(0, WalletTransaction::query()->count());
            }
        }
        Http::fake(['gateway.example/api/pay/query_order' => Http::response($this->signed([...$base, 'mchOrderNo' => 'different-order']))]);
        $this->expectExceptionMessage('YDPAY query returned a different deposit order.');
        app(YdPayRechargeService::class)->sync($order);
    }

    public function test_deposit_sync_and_scheduler_route_only_to_ydpay(): void
    {
        $order = $this->deposit();
        Http::fake(['gateway.example/api/pay/query_order' => Http::response($this->depositPayload($order, '1'))]);
        app(Qc168CallbackService::class)->sync($order);
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        Http::assertSentCount(2);
        Http::assertSent(fn ($http) => str_ends_with($http->url(), '/api/pay/query_order')
            && $http['mchOrderNo'] === $order->merchant_order_id && $http['executeNotify'] === 'false');
        $this->assertSame('pending', $order->fresh()->gateway_payment_status);
        $this->assertNotNull($order->fresh()->gateway_last_synced_at);
        $order->update(['gateway_provider' => 'unknown']);
        $this->assertFalse(app(Qc168CallbackService::class)->sync($order)['accepted']);
        Http::assertSentCount(2);
    }

    public function test_unpaid_deposit_expires_at_sixty_minutes_once_and_is_visible_as_expired(): void
    {
        $this->freezeTime();
        $order = $this->deposit();
        $order->update(['gateway_payment_url' => 'https://cashier.example/pay']);
        Http::fake(['gateway.example/api/pay/query_order' => Http::response($this->depositPayload($order, '1'))]);
        $this->travel(59)->minutes();
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        $this->assertFalse($order->fresh()->isGatewayExpired());
        $this->travel(1)->minutes();
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        $this->assertTrue($order->fresh()->isGatewayExpired());
        $this->assertSame('1', $order->fresh()->gateway_meta['ydpay_status']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(now()->toAtomString(), $order->fresh()->gateway_meta['deposit_expired_at']);
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        app(YdPayRechargeService::class)->process($this->depositPayload($order, '0'), 'sync');
        $this->assertTrue($order->fresh()->isGatewayExpired());
        $this->assertSame(1, PaymentGatewayEvent::query()->where('event_type', 'ydpay.deposit.expired')->count());
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame(0, Wallet::query()->count());
        app()->setLocale('en');
        $this->actingAs($order->user)->getJson(route('recharge.gateway.status', $order))
            ->assertOk()->assertJsonPath('gateway_status', 'expired')->assertJsonPath('payment_confirmed', false)
            ->assertJsonPath('gateway_status_label', 'Expired locally (unpaid)');
        $this->get(route('recharge.gateway.return', $order))->assertOk()->assertSee(__('ydpay.deposit_expired_help'));
        $this->assertNull(ApiFormatter::rechargeRequest($order->fresh())['payment_url']);
        $this->loginAdmin();
        Livewire::test(ListRechargeRequests::class)->assertSee('Expired locally (unpaid)');
        Livewire::test(ViewRechargeRequest::class, ['record' => $order->getRouteKey()])->assertSee('Expired locally (unpaid)');
    }

    public function test_deposit_expiry_uses_the_creation_setting_snapshot(): void
    {
        $this->freezeTime();
        Setting::setValue('ydpay_deposit_expiry_minutes', 90);
        Http::fake(['gateway.example/api/pay/create_order' => Http::response($this->signed([
            'retCode' => '0', 'payOrderId' => '123456789012345', 'payJumpUrl' => 'https://cashier.example/pay',
        ]))]);
        $order = app(YdPayRechargeService::class)->create($this->user(), '50000.00');
        $this->assertSame(now()->addMinutes(90)->toAtomString(), $order->gateway_meta['deposit_expires_at']);
        Setting::setValue('ydpay_deposit_expiry_minutes', 60);
        $this->travel(61)->minutes();
        $service = app(YdPayRechargeService::class);
        $this->assertSame('pending', $service->process($this->depositPayload($order, '1'), 'sync'));
        $this->travel(29)->minutes();
        $this->assertSame('expired', $service->process($this->depositPayload($order, '1'), 'sync'));
    }

    #[DataProvider('invalidExpiryQueries')]
    public function test_expiry_requires_a_successful_verified_query(string $error): void
    {
        $order = $this->deposit();
        $order->forceFill(['created_at' => now()->subHours(2), 'gateway_payment_status' => 'pending'])->save();
        $payload = $this->depositPayload($order, '1');
        if ($error === 'signature') {
            $payload['sign'] = 'INVALID';
        } elseif ($error === 'amount') {
            $payload = $this->signed([...$payload, 'amount' => '1']);
        } elseif ($error === 'order') {
            $payload = $this->signed([...$payload, 'mchOrderNo' => 'wrong-order']);
        }
        Http::fake(['gateway.example/api/pay/query_order' => fn () => $error === 'transport'
            ? throw new ConnectionException('Query unavailable') : Http::response($payload)]);
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->gateway_payment_status);
        $this->assertSame(0, PaymentGatewayEvent::query()->where('event_type', 'ydpay.deposit.expired')->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public static function invalidExpiryQueries(): array
    {
        return [['transport'], ['signature'], ['amount'], ['order']];
    }

    #[DataProvider('latePaymentSources')]
    public function test_expired_deposit_late_payment_is_reconciled_exactly_once(bool $callbackFirst): void
    {
        $order = $this->deposit();
        $order->forceFill(['created_at' => now()->subHours(2)])->save();
        $service = app(YdPayRechargeService::class);
        $this->assertSame('expired', $service->process($this->depositPayload($order, '1'), 'sync'));
        $callback = $this->signed([...$this->depositPayload($order, '2'), 'backType' => '2', 'income' => '5000000']);
        Http::fake(['gateway.example/api/pay/query_order' => Http::response($this->depositPayload($order, '2'))]);
        if ($callbackFirst) {
            $this->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($callback))->assertOk();
        }
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        $this->post('/payment-gateway/ydpay/deposit/callback?'.http_build_query($callback))->assertOk();
        $service->process($this->depositPayload($order, '3'), 'sync');
        // A stale unpaid response after the paid callback must not expire the order again.
        $service->process($this->depositPayload($order, '1'), 'sync');
        $this->assertSame('completed', $order->fresh()->gateway_payment_status);
        $this->assertTrue($order->fresh()->isPaymentConfirmed());
        $this->assertNotNull($order->fresh()->gateway_meta['deposit_expired_at']);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame('50000.00', Wallet::query()->sole()->balance);
        $this->assertSame(1, PaymentGatewayEvent::query()->where('event_type', 'ydpay.deposit.expired')->count());
    }

    public static function latePaymentSources(): array
    {
        return ['callback first' => [true], 'query first' => [false]];
    }

    #[DataProvider('depositStates')]
    public function test_overdue_deposit_only_expires_for_verified_unpaid_provider_states(string $status, string $state, bool $paid): void
    {
        $order = $this->deposit();
        $order->forceFill(['created_at' => now()->subHours(2)])->save();
        $result = app(YdPayRechargeService::class)->process($this->depositPayload($order, $status), 'sync');
        $this->assertSame(in_array($status, ['0', '1'], true) ? 'expired' : $state, $result);
        $this->assertSame($paid, $order->fresh()->isPaymentConfirmed());
        $this->assertSame($paid ? 1 : 0, WalletTransaction::query()->count());
    }

    public function test_expiry_setting_validation_rejects_invalid_durations_without_saving(): void
    {
        $admin = $this->loginAdmin();
        $settings = app(YdPaySettings::class);
        foreach ([0, 1441, 1.5, null] as $minutes) {
            try {
                $settings->save(['deposit_enabled' => true, 'withdraw_enabled' => false,
                    'withdraw_release_hours' => 24, 'deposit_expiry_minutes' => $minutes], $admin);
                $this->fail('Invalid expiry duration must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('deposit_expiry_minutes', $exception->errors());
            }
        }
        $this->assertSame(60, $settings->depositExpiryMinutes());
        $this->assertSame(0, PaymentGatewayEvent::query()->where('event_type', 'ydpay.settings.updated')->count());
    }

    public function test_ydpay_expiry_scheduler_does_not_touch_manual_or_qc168_deposits(): void
    {
        $order = $this->deposit();
        $order->forceFill(['gateway_provider' => 'qc168', 'gateway_payment_status' => 'pending', 'created_at' => now()->subDays(2)])->save();
        $manual = $order->replicate();
        $manual->forceFill(['gateway_provider' => null, 'deposit_method' => 'manual', 'merchant_order_id' => null])->save();
        $this->artisan('ydpay:process-orders deposit')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->gateway_payment_status);
        $this->assertSame('pending', $manual->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_sandbox_create_is_quarantined_and_cannot_later_credit(): void
    {
        Http::fake(['gateway.example/api/pay/create_order' => Http::response($this->signed([
            'retCode' => '0', 'payOrderId' => '123456789012345', 'payJumpUrl' => 'https://cashier.example/pay', 'sandbox' => 1,
        ]))]);
        try {
            app(YdPayRechargeService::class)->create($this->user(), '50000');
            $this->fail('Sandbox order must not reach a real cashier redirect.');
        } catch (\RuntimeException) {
            $order = RechargeRequest::query()->sole();
            $this->assertSame('sandbox_review', $order->gateway_payment_status);
            $this->assertNull($order->gateway_payment_url);
        }
        try {
            app(YdPayRechargeService::class)->process($this->depositPayload($order, '2'), 'sync');
            $this->fail('Quarantined sandbox order must never credit.');
        } catch (\RuntimeException) {
            $this->assertSame(0, WalletTransaction::query()->count());
        }
    }

    #[DataProvider('payoutStates')]
    public function test_every_payout_query_status_uses_ydpay_semantics_and_keeps_unpaid_funds_locked(string $status, string $state): void
    {
        [$request, $attempt] = $this->payout();
        $payload = $this->signed([...$this->payoutPayload($attempt->merchant_order_id, $status), 'retCode' => '0']);
        app(YdPayWithdrawalService::class)->process($payload, 'sync');
        $this->assertSame($state, $request->fresh()->payout_status);
        $this->assertSame($status === '2' ? '120000.00' : '420000.00', Wallet::query()->sole()->locked_balance);
        $this->assertSame('18455.00', Wallet::query()->sole()->balance);
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame($status === '3', $request->fresh()->canCancelPayout());
        $this->assertNotNull($request->fresh()->gateway_last_synced_at);
    }

    public static function payoutStates(): array
    {
        return [['0', 'processing'], ['1', 'processing'], ['2', 'completed'], ['3', 'failed'],
            ['4', 'processing'], ['5', 'processing'], ['6', 'refund_review']];
    }

    public function test_failed_payout_cannot_regress_and_old_attempt_sync_is_never_sent_to_qc168(): void
    {
        [$request, $attempt] = $this->payout();
        foreach (['3', '1', '4', '5'] as $status) {
            app(YdPayWithdrawalService::class)->process($this->signed([...$this->payoutPayload($attempt->merchant_order_id, $status), 'retCode' => '0']), 'sync');
        }
        $this->assertSame('failed', $attempt->fresh()->gateway_status);
        $this->assertSame('failed', $request->fresh()->payout_status);
        try {
            app(Qc168WithdrawalPayoutService::class)->syncAttempt($attempt, 'sync');
            $this->fail('A YDPAY attempt cannot be sent to QC168.');
        } catch (\RuntimeException) {
            Http::assertNothingSent();
        }
    }

    public function test_instant_approval_dispatches_ydpay_with_zero_delay_and_one_attempt(): void
    {
        [$request] = $this->payout(false);
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        Http::fake(function ($http) {
            if (str_ends_with($http->url(), '/api/agentpay/apply')) {
                return Http::response($this->signed(['retCode' => '0', 'agentpayOrderId' => '123456789012346', 'gatewayId' => '22']));
            }

            return Http::response($this->signed([...$this->payoutPayload($http['mchOrderNo'], '5'), 'retCode' => '0']));
        });
        $approved = app(YdPayWithdrawalService::class)->approve($request, $admin, 'Instant YDPAY approval', true);
        $this->assertSame('gateway_instant', $approved->approval_mode);
        $this->assertSame(0, $approved->gateway_release_delay_hours_snapshot);
        $this->assertTrue($approved->reviewed_at->equalTo($approved->gateway_release_at));
        $this->assertSame('processing', $approved->payout_status);
        $this->assertSame('5', $approved->gateway_meta['ydpay_status']);
        $this->assertSame(1, $approved->payoutAttempts()->count());
        $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
        Http::assertSentCount(2);
        $this->assertDatabaseHas('payment_gateway_events', ['provider' => 'ydpay', 'event_type' => 'withdrawal.approved_gateway_instant']);
    }

    public function test_retry_and_cancel_require_fresh_failure_even_when_local_failure_is_preserved(): void
    {
        [$request, $attempt] = $this->payout();
        $admin = $this->loginAdmin();
        $service = app(YdPayWithdrawalService::class);
        $service->process($this->payoutPayload($attempt->merchant_order_id, '3'), 'callback');
        Http::fake(['gateway.example/api/agentpay/query_order' => Http::response($this->signed([
            ...$this->payoutPayload($attempt->merchant_order_id, '1'), 'retCode' => '0',
        ]))]);
        foreach (['retry', 'cancel'] as $operation) {
            try {
                $service->{$operation}($request->fresh(), $admin, 'Verify fresh state');
                $this->fail('A fresh processing result cannot authorize a new payout or refund.');
            } catch (\RuntimeException) {
                $this->assertSame('failed', $request->fresh()->payout_status);
                $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
                $this->assertSame(1, $request->payoutAttempts()->count());
                $this->assertSame(0, WalletTransaction::query()->count());
            }
        }
        Http::assertSentCount(2);
    }

    public function test_uncertain_retry_never_displays_the_previous_attempt_as_current_failure(): void
    {
        [$request, $attempt] = $this->payout();
        $admin = $this->loginAdmin();
        $failure = $this->signed([...$this->payoutPayload($attempt->merchant_order_id, '3'), 'retCode' => '0']);
        Http::fake(function ($http) use ($failure) {
            if (str_ends_with($http->url(), '/api/agentpay/query_order')) {
                return Http::response($failure);
            }
            throw new ConnectionException('Create timeout');
        });
        try {
            app(YdPayWithdrawalService::class)->retry($request, $admin, 'Retry confirmed failure');
            $this->fail('Ambiguous submission must be reported.');
        } catch (\RuntimeException) {
            $this->assertSame('create_pending', $request->fresh()->gateway_payment_status);
            $this->assertArrayNotHasKey('ydpay_status', $request->fresh()->gateway_meta);
            $this->assertSame('failed', $attempt->fresh()->gateway_status);
            $this->assertSame(2, $request->payoutAttempts()->count());
            $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
        }
    }

    private function depositPayload(RechargeRequest $order, string $status): array
    {
        return $this->signed(['retCode' => '0', 'mchId' => '502002001', 'gatewayId' => $order->gateway_channel,
            'mchOrderNo' => $order->merchant_order_id, 'payOrderId' => '123456789012345',
            'amount' => '5000000', 'currency' => 'VND', 'status' => $status]);
    }

    public function test_admin_recharge_list_and_detail_identify_provider_and_sync_ydpay(): void
    {
        $order = $this->deposit();
        $other = $order->replicate();
        $other->forceFill(['gateway_provider' => 'qc168', 'merchant_order_id' => 'QC-OTHER'])->save();
        $this->loginAdmin();
        app()->setLocale('en');
        Http::fake(['gateway.example/api/pay/query_order' => Http::response($this->depositPayload($order, '1'))]);
        Livewire::test(ListRechargeRequests::class)
            ->assertCanSeeTableRecords([$order, $other])
            ->assertActionHidden(TestAction::make('syncGateway')->table($other))
            ->assertActionHidden(TestAction::make('syncGatewayAttention')->table($other))
            ->filterTable('gateway_provider', 'ydpay')->assertCanSeeTableRecords([$order])->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ViewRechargeRequest::class, ['record' => $other->getRouteKey()])
            ->assertActionHidden('syncGateway');
        Livewire::test(ViewRechargeRequest::class, ['record' => $order->getRouteKey()])
            ->assertSee('YDPAY')->assertActionVisible('syncGateway')->callAction('syncGateway')->assertHasNoActionErrors()
            ->assertRedirect();
        Livewire::test(ViewRechargeRequest::class, ['record' => $order->getRouteKey()])
            ->assertSee('Awaiting payment');
        Http::assertSentCount(1);
        $this->assertSame('pending', $order->fresh()->gateway_payment_status);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_admin_withdrawal_list_and_detail_offer_ydpay_modes_and_hide_retired_qc168_actions(): void
    {
        [$request] = $this->payout(false);
        $this->loginAdmin();
        app()->setLocale('en');
        config(['services.qc168.enabled' => true]);
        $this->mock(Qc168Gateway::class)->shouldReceive('isWithdrawalAvailable')->andReturn(true);
        $view = Livewire::test(ViewWithdrawRequest::class, ['record' => $request->getRouteKey()])
            ->assertActionEnabled('approve_ydpay')->assertActionEnabled('approve_ydpay_instant')
            ->assertSee('YDPAY · Approve')->assertDontSee('QC168 · Approve')
            ->assertActionHidden('approve_gateway_scheduled')->assertActionHidden('approve_gateway_instant');
        Livewire::test(ListWithdrawRequests::class)
            ->assertActionEnabled(TestAction::make('approve_ydpay')->table($request))
            ->assertActionEnabled(TestAction::make('approve_ydpay_instant')->table($request))
            ->assertActionHidden(TestAction::make('approve_gateway_scheduled')->table($request))
            ->assertActionHidden(TestAction::make('approve_gateway_instant')->table($request));
        Setting::setValue('ydpay_withdraw_enabled', false);
        $view->assertActionDisabled('approve_ydpay')->assertActionDisabled('approve_ydpay_instant')->assertActionEnabled('reject');
        Http::assertNothingSent();
    }

    public function test_payout_sandbox_or_mismatched_query_evidence_never_finalizes(): void
    {
        [$request, $attempt] = $this->payout();
        foreach ([['sandbox' => '1'], ['amount' => '30000000'], ['gatewayId' => '19'], ['status' => 'completed'], ['retCode' => '9999']] as $changes) {
            try {
                app(YdPayWithdrawalService::class)->process($this->signed([
                    ...$this->payoutPayload($attempt->merchant_order_id, '2'), 'retCode' => '0', ...$changes,
                ]), 'sync');
                $this->fail('Mismatched payout evidence must not finalize.');
            } catch (\RuntimeException) {
                $this->assertSame('submitted', $request->fresh()->payout_status);
                $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
            }
        }
    }

    public function test_sandbox_payout_creation_stops_before_query_or_finalization(): void
    {
        [$request] = $this->payout(false);
        $admin = $this->loginAdmin();
        Http::fake(['gateway.example/api/agentpay/apply' => Http::response($this->signed([
            'retCode' => '0', 'agentpayOrderId' => '123456789012346', 'gatewayId' => '22', 'sandbox' => '1',
        ]))]);
        try {
            app(YdPayWithdrawalService::class)->approve($request, $admin, instant: true);
            $this->fail('A sandbox payout cannot finalize a real withdrawal.');
        } catch (\RuntimeException) {
            $this->assertSame('refund_review', $request->fresh()->payout_status);
            $this->assertSame('sandbox_response', $request->fresh()->gateway_meta['environment_review']);
            $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
            $this->assertSame(0, WalletTransaction::query()->count());
            Http::assertSentCount(1);
        }
    }

    public function test_instant_approval_permission_is_enforced_and_transport_failure_keeps_reserve(): void
    {
        [$request] = $this->payout(false);
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        $admin->givePermissionTo(Permission::findOrCreate('ApproveScheduledGateway:Withdrawal', 'admin'));
        try {
            app(YdPayWithdrawalService::class)->approve($request, $admin, instant: true);
            $this->fail('Scheduled permission cannot authorize instant payout.');
        } catch (AuthorizationException) {
            $this->assertSame('pending', $request->fresh()->status);
        }
        $admin->givePermissionTo(Permission::findOrCreate('ApproveInstantGateway:Withdrawal', 'admin'));
        Http::fake(fn () => throw new ConnectionException('Timeout'));
        try {
            app(YdPayWithdrawalService::class)->approve($request, $admin, instant: true);
            $this->fail('Transport failure should be reported.');
        } catch (\RuntimeException) {
            $this->assertSame('submitted', $request->fresh()->payout_status);
            $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
            $this->assertSame(1, $request->payoutAttempts()->count());
            $this->assertNull(app(YdPayWithdrawalService::class)->dispatch($request->fresh()));
        }
    }

    public function test_payout_submission_and_query_errors_are_audited_and_visible_without_releasing_funds(): void
    {
        [$request] = $this->payout(false);
        $this->loginAdmin();
        app()->setLocale('en');
        config(['third_party.outbound_proxy' => 'http://sensitive-user:sensitive-password@proxy.example:3128']);
        Http::fake(function ($http) {
            if (str_ends_with($http->url(), '/apply')) {
                return Http::response(['retCode' => 'AMOUNT_INVALID',
                    'retMsg' => 'Amount rejected <script>bad()</script> DOC_DEMO_KEY_ONLY sensitive-user sensitive-password '.$http['sign'],
                    'secret' => 'NEVER_STORE_BODY', 'sign' => str_repeat('a', 32)]);
            }

            return Http::response(['retCode' => '9999', 'retMsg' => 'Payout order not found']);
        });
        Livewire::test(ListWithdrawRequests::class)
            ->callAction(TestAction::make('approve_ydpay_instant')->table($request), ['admin_remark' => 'Diagnostic test'])
            ->assertNotified();
        $attempt = $request->payoutAttempts()->sole();
        $this->assertStringContainsString('AMOUNT_INVALID', $attempt->failure_reason);
        try {
            app(YdPayWithdrawalService::class)->sync($request->fresh());
            $this->fail('An unsigned provider error cannot be payment evidence.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('9999', $exception->getMessage());
        }
        $request->refresh();
        $this->assertSame('AMOUNT_INVALID', $request->gateway_meta['ydpay_submission_error']['retCode']);
        $this->assertSame('9999', $request->gateway_meta['ydpay_last_error']['retCode']);
        $this->assertSame('submitted', $request->payout_status);
        $this->assertSame('create_pending', $request->gateway_payment_status);
        $this->assertSame(1, $request->payoutAttempts()->count());
        $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
        $this->assertSame(0, WalletTransaction::query()->count());
        $events = PaymentGatewayEvent::query()->where('event_type', 'ydpay.request.error')->get();
        $this->assertCount(2, $events);
        $this->assertFalse($events->first()->signature_valid);
        $this->assertSame($attempt->id, $events->first()->withdrawal_payout_attempt_id);
        $stored = $events->toJson().$request->toJson().$attempt->fresh()->toJson();
        foreach (['DOC_DEMO_KEY_ONLY', 'sensitive-user', 'sensitive-password', 'NEVER_STORE_BODY', str_repeat('a', 32), '<script>'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }
        Livewire::test(ListWithdrawRequests::class)->assertSee('Payout order not found');
        Livewire::test(ViewWithdrawRequest::class, ['record' => $request->getRouteKey()])
            ->assertSee('AMOUNT_INVALID')->assertSee('Payout order not found');
        Http::assertSentCount(2);
    }

    public function test_deposit_errors_preserve_original_reason_and_show_in_admin(): void
    {
        Http::fake(['gateway.example/*' => Http::response(['retCode' => 'LIMIT', 'retMsg' => 'Deposit limit exceeded'])]);
        try {
            app(YdPayRechargeService::class)->create($this->user(), '50000');
            $this->fail('Provider rejection must be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Deposit limit exceeded', $exception->getMessage());
        }
        $order = RechargeRequest::query()->sole();
        $this->assertSame('LIMIT', $order->gateway_meta['ydpay_submission_error']['retCode']);
        $this->assertSame('pending', $order->status);
        $event = PaymentGatewayEvent::query()->where('event_type', 'ydpay.request.error')->sole();
        $this->assertSame($order->id, $event->recharge_request_id);
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->loginAdmin();
        Livewire::test(ListRechargeRequests::class)->assertSee('Deposit limit exceeded');
        Livewire::test(ViewRechargeRequest::class, ['record' => $order->getRouteKey()])->assertSee('Deposit limit exceeded');
        app(YdPayRechargeService::class)->process($this->depositPayload($order, '2'), 'sync');
        $this->assertTrue($order->fresh()->isPaymentConfirmed());
        $this->assertNull($order->fresh()->gateway_fail_reason);
        $this->assertSame('LIMIT', $order->fresh()->gateway_meta['ydpay_submission_error']['retCode']);
    }

    #[DataProvider('diagnosticResponses')]
    public function test_non_payment_http_errors_are_diagnostic_only(mixed $body, int $httpStatus, string $kind): void
    {
        [$request, $attempt] = $this->payout();
        Http::fake(['gateway.example/*' => Http::response($body, $httpStatus)]);
        try {
            app(YdPayWithdrawalService::class)->sync($request);
            $this->fail('Invalid evidence must not update financial status.');
        } catch (\RuntimeException) {
            $event = PaymentGatewayEvent::query()->where('event_type', 'ydpay.request.error')->sole();
            $this->assertSame($kind, $event->payload['kind']);
            $this->assertSame($httpStatus, $event->payload['http_status']);
            $this->assertSame('create_pending', $attempt->fresh()->gateway_status);
            $this->assertSame('submitted', $request->fresh()->payout_status);
            $this->assertSame('420000.00', Wallet::query()->sole()->locked_balance);
        }
    }

    public static function diagnosticResponses(): array
    {
        return [
            ['<html>DO_NOT_STORE_BODY</html>', 502, 'invalid_json'],
            [['retCode' => '0', 'sign' => 'invalid'], 200, 'invalid_signature'],
            [['retCode' => 'DENIED', 'retMsg' => 'Access denied'], 403, 'http_error'],
            [['retCode' => ['malformed'], 'retMsg' => ['malformed']], 200, 'provider_error'],
        ];
    }

    public function test_query_error_after_completed_callback_cannot_regress_or_overwrite_the_financial_result(): void
    {
        [$request, $attempt] = $this->payout();
        Http::fake(function () use ($attempt) {
            app(YdPayWithdrawalService::class)->process($this->payoutPayload($attempt->merchant_order_id, '2'), 'callback');

            return Http::response(['retCode' => '9999', 'retMsg' => 'Order not found']);
        });
        try {
            app(YdPayWithdrawalService::class)->sync($request);
            $this->fail('Query error must be reported separately.');
        } catch (\RuntimeException) {
            $this->assertSame('completed', $request->fresh()->payout_status);
            $this->assertSame('completed', $attempt->fresh()->gateway_status);
            $this->assertNull($request->fresh()->gateway_fail_reason);
            $this->assertSame('120000.00', Wallet::query()->sole()->locked_balance);
            $this->assertSame(0, WalletTransaction::query()->count());
            $this->assertSame(1, PaymentGatewayEvent::query()->where('event_type', 'ydpay.request.error')->count());
        }
    }

    private function loginAdmin(): AdminUser
    {
        $admin = AdminUser::query()->create(['username' => 'yd-admin', 'password' => bcrypt('password'), 'status' => 'active']);
        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');

        return $admin;
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
