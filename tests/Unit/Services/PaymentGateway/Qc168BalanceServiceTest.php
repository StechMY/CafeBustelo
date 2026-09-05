<?php

namespace Tests\Unit\Services\PaymentGateway;

use App\Services\PaymentGateway\Qc168BalanceService;
use App\Services\PaymentGateway\Qc168Gateway;
use Filament\PanelRegistry;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class Qc168BalanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.qc168.enabled' => true,
            'services.qc168.base_url' => 'https://gateway.example',
            'services.qc168.oc_key' => 'test-key',
            'services.qc168.secret' => 'test-secret',
            'services.qc168.merchant_uid' => 'merchant-123',
            'services.qc168.currency' => 'vnd',
            'services.qc168.country' => null,
            'services.qc168.payment' => null,
            'services.qc168.deposit_payment' => null,
            'services.qc168.withdraw_payment' => null,
            'sms.enabled' => false,
        ]);

        Cache::flush();
        Carbon::setTestNow('2026-09-05 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_merchant_ledger_reuses_signed_transport_and_only_requires_ledger_configuration(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame(
                'https://gateway.example/octopus/api/ledger/get_merchant_ledger',
                $request->url(),
            );
            $this->assertSame('POST', $request->method());
            $this->assertSame(['merchant_uid' => 'merchant-123'], $request->data());
            $this->assertTrue($request->hasHeader('OC-Key', 'test-key'));
            $this->assertTrue($request->hasHeader(
                'OC-Signature',
                hash_hmac('sha256', 'merchant_uid=merchant-123', 'test-secret'),
            ));

            return Http::response($this->ledgerResponse('125000000.000000'));
        });

        $response = app(Qc168Gateway::class)->getMerchantLedger();

        $this->assertSame('success', $response['result']);
        Http::assertSentCount(1);
    }

    public function test_successful_balance_is_cached_as_an_exact_decimal_string(): void
    {
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response(
                $this->ledgerResponse('125000000.000000'),
            ),
        ]);

        $service = app(Qc168BalanceService::class);
        $first = $service->getBalance();
        $second = $service->getBalance();

        $this->assertSame('CafeBustelo', $first['merchant_name']);
        $this->assertSame('vnd', $first['currency']);
        $this->assertSame('125000000.000000', $first['balance']);
        $this->assertSame('125000000.000000', Cache::get(Qc168BalanceService::LAST_SUCCESS_KEY)['balance']);
        $this->assertSame('healthy', $first['health']);
        $this->assertTrue($first['is_available']);
        $this->assertFalse($first['is_stale']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_balance_refreshes_after_the_five_minute_cache_window(): void
    {
        Http::fakeSequence()
            ->push($this->ledgerResponse('100.000000'))
            ->push($this->ledgerResponse('200.000000'));

        $service = app(Qc168BalanceService::class);
        $this->assertSame('100.000000', $service->getBalance()['balance']);

        Carbon::setTestNow(now()->addSeconds(299));
        $this->assertSame('100.000000', $service->getBalance()['balance']);
        Http::assertSentCount(1);

        Carbon::setTestNow(now()->addSeconds(2));
        $this->assertSame('200.000000', $service->getBalance()['balance']);
        Http::assertSentCount(2);
    }

    public function test_manual_refresh_has_a_global_cooldown(): void
    {
        Http::fakeSequence()
            ->push($this->ledgerResponse('100.000000'))
            ->push($this->ledgerResponse('200.000000'));

        $service = app(Qc168BalanceService::class);
        $this->assertSame('100.000000', $service->getBalance()['balance']);
        $this->assertSame('200.000000', $service->refresh()['balance']);
        $this->assertSame('200.000000', $service->refresh()['balance']);

        Http::assertSentCount(2);
    }

    public function test_refresh_lock_prevents_a_second_provider_request(): void
    {
        Http::fake();
        $lock = Cache::lock(Qc168BalanceService::REFRESH_LOCK_KEY, 20);
        $this->assertTrue($lock->get());

        try {
            $result = app(Qc168BalanceService::class)->getBalance();
        } finally {
            $lock->release();
        }

        $this->assertNull($result['balance']);
        Http::assertNothingSent();
    }

    public function test_provider_failure_keeps_the_last_successful_balance_stale(): void
    {
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response(
                $this->ledgerResponse('125000000.000000'),
            ),
        ]);

        $service = app(Qc168BalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->addSeconds(31));
        Http::fake(fn () => throw new ConnectionException('request containing test-secret failed'));

        $result = $service->getBalance();

        $this->assertSame('125000000.000000', $result['balance']);
        $this->assertTrue($result['is_stale']);
        $this->assertFalse($result['is_available']);
        $this->assertSame('provider_error', $result['health']);
    }

    public function test_failure_without_a_previous_balance_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertNull($result['balance']);
        $this->assertFalse($result['is_available']);
        $this->assertSame('provider_error', $result['health']);
    }

    public function test_zero_is_a_valid_danger_balance(): void
    {
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response(
                $this->ledgerResponse('0.000000'),
            ),
        ]);

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertSame('0.000000', $result['balance']);
        $this->assertTrue($result['is_available']);
        $this->assertFalse($result['is_stale']);
        $this->assertSame('danger', $result['health']);
    }

    public function test_configured_currency_is_used_instead_of_hardcoded_vnd(): void
    {
        config(['services.qc168.currency' => 'IDR']);
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response([
                'result' => 'success',
                'data' => [
                    'merchant_name' => 'CafeBustelo',
                    'vnd' => '125000000.000000',
                    'idr' => '900000.500000',
                ],
            ]),
        ]);

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertSame('idr', $result['currency']);
        $this->assertSame('900000.500000', $result['balance']);
    }

    public function test_missing_configured_currency_preserves_the_last_known_balance(): void
    {
        Http::fakeSequence()
            ->push($this->ledgerResponse('125000000.000000'))
            ->push([
                'result' => 'success',
                'data' => ['merchant_name' => 'CafeBustelo', 'usd' => '10.000000'],
            ]);

        $service = app(Qc168BalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->addSeconds(31));

        $result = $service->getBalance();

        $this->assertSame('125000000.000000', $result['balance']);
        $this->assertTrue($result['is_stale']);
        $this->assertSame('provider_error', $result['health']);
    }

    public function test_disabled_gateway_does_not_call_provider(): void
    {
        config(['services.qc168.enabled' => false]);
        Http::fake();

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertNull($result['balance']);
        $this->assertSame('disabled', $result['health']);
        Http::assertNothingSent();
    }

    public function test_incomplete_ledger_configuration_does_not_call_provider(): void
    {
        config(['services.qc168.secret' => null]);
        Http::fake();

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertNull($result['balance']);
        $this->assertSame('configuration_incomplete', $result['health']);
        Http::assertNothingSent();
    }

    public function test_invalid_provider_balance_is_not_treated_as_zero(): void
    {
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response(
                $this->ledgerResponse('not-a-number'),
            ),
        ]);

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertNull($result['balance']);
        $this->assertFalse($result['is_available']);
        $this->assertSame('provider_error', $result['health']);
        $this->assertSame('INVALID_BALANCE', $result['error_code']);
    }

    public function test_provider_result_error_is_only_a_balance_query_failure(): void
    {
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response([
                'result' => 'error',
                'data' => 'Provider rejected the ledger query.',
            ]),
        ]);

        $result = app(Qc168BalanceService::class)->getBalance();

        $this->assertNull($result['balance']);
        $this->assertFalse($result['is_available']);
        $this->assertSame('provider_error', $result['health']);
        $this->assertSame('REQUEST_FAILED', $result['error_code']);
    }

    public function test_component_formats_vnd_without_exposing_credentials(): void
    {
        config([
            'services.qc168.oc_key' => 'browser-key-must-not-appear',
            'services.qc168.secret' => 'browser-secret-must-not-appear',
        ]);
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response(
                $this->ledgerResponse('125000000.000000'),
            ),
        ]);

        $html = Livewire::test('admin-qc168-balance-indicator')->html();

        $this->assertStringContainsString('QC168 Balance', $html);
        $this->assertStringContainsString('₫125,000,000', $html);
        $this->assertStringNotContainsString('browser-key-must-not-appear', $html);
        $this->assertStringNotContainsString('browser-secret-must-not-appear', $html);
    }

    public function test_component_renders_unavailable_without_throwing(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        Livewire::test('admin-qc168-balance-indicator')
            ->assertSee(__('filament.qc168_balance_unavailable'));
    }

    public function test_topbar_renders_sms_then_qc168_before_search(): void
    {
        Http::fake([
            '*/octopus/api/ledger/get_merchant_ledger' => Http::response(
                $this->ledgerResponse('125000000.000000'),
            ),
        ]);

        app(PanelRegistry::class)->get('admin')->boot();
        $html = FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE)->toHtml();
        $smsPosition = strpos($html, 'data-balance-provider="sms"');
        $qc168Position = strpos($html, 'data-balance-provider="qc168"');

        $this->assertNotFalse($smsPosition);
        $this->assertNotFalse($qc168Position);
        $this->assertLessThan($qc168Position, $smsPosition);
    }

    private function ledgerResponse(string $vnd): array
    {
        return [
            'result' => 'success',
            'data' => [
                'merchant_name' => 'CafeBustelo',
                'vnd' => $vnd,
                'usd' => '0.000000',
            ],
        ];
    }
}
