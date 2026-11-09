<?php

namespace Tests\Unit\Services\PaymentGateway;

use App\Services\PaymentGateway\Qc168BalanceService;
use App\Services\PaymentGateway\YdPayBalanceService;
use App\Services\PaymentGateway\YdPayGateway;
use Filament\PanelRegistry;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class YdPayBalanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'sms.enabled' => false,
            'services.ydpay.enabled' => true, 'services.ydpay.deposit_enabled' => false,
            'services.ydpay.withdraw_enabled' => false, 'services.ydpay.merchant_id' => '502002001',
            'services.ydpay.secret' => 'balance-test-secret', 'services.ydpay.base_url' => 'https://gateway.example',
            'third_party.outbound_proxy' => 'http://proxy.example:3128']);
        Cache::flush();
        Carbon::setTestNow('2026-11-09 12:00:00');
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();
        parent::tearDown();
    }

    public function test_balance_uses_signed_proxy_transport_without_business_switches_and_converts_exact_units(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertSame('https://gateway.example/api/agentpay/query_balance', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertStringContainsString('application/x-www-form-urlencoded', $request->header('Content-Type')[0]);
            $this->assertEqualsCanonicalizing(['mchId', 'version', 'reqTime', 'sign'], array_keys($request->data()));
            $this->assertSame('502002001', $request['mchId']);
            $this->assertTrue(app(YdPayGateway::class)->verify($request->data()));
            $this->assertSame('http://proxy.example:3128', $options['proxy']);
            $this->assertTrue($options['verify']);
            $this->assertFalse($options['allow_redirects']);

            return Http::response($this->response('123456789012345678901'));
        });

        $state = app(YdPayBalanceService::class)->getBalance();
        $this->assertSame('1234567890123456789.01', $state['balance']);
        $this->assertSame('vnd', $state['currency']);
        $this->assertTrue($state['is_available']);
        Http::assertSentCount(1);
    }

    public function test_zero_and_fractional_minor_units_are_valid(): void
    {
        $responses = Http::fakeSequence();
        foreach (['0', '1', '000100'] as $minor) {
            $responses->push($this->response($minor));
        }
        foreach (['0' => '0.00', '1' => '0.01', '000100' => '1.00'] as $minor => $expected) {
            Cache::flush();
            $state = app(YdPayBalanceService::class)->getBalance();
            $this->assertSame($expected, $state['balance']);
            $this->assertTrue($state['is_available']);
            $this->assertSame($expected === '0.00' ? 'danger' : 'healthy', $state['health']);
        }
    }

    public function test_invalid_or_unverified_available_balance_never_uses_the_total_balance(): void
    {
        $unsigned = $this->response('12300');
        unset($unsigned['sign']);
        $tampered = $this->response('12300');
        $tampered['availableAgentpayBalance'] = '45600';
        $responses = [$unsigned, $tampered, $this->signed(['retCode' => '1', 'availableAgentpayBalance' => '12300'])];
        foreach ([null, false, '1.2', '-100', '1e5', ' 100 ', []] as $invalid) {
            $responses[] = $this->response($invalid);
        }
        $responses[] = $this->signed(['retCode' => '0', 'agentpayBalance' => '99900']);

        $sequence = Http::fakeSequence();
        foreach ($responses as $response) {
            $sequence->push($response);
        }

        foreach ($responses as $response) {
            Cache::flush();
            $state = app(YdPayBalanceService::class)->getBalance();
            $this->assertNull($state['balance']);
            $this->assertFalse($state['is_available']);
            $this->assertSame('provider_error', $state['health']);
        }
    }

    public function test_failed_refresh_keeps_last_success_and_retries_after_thirty_seconds(): void
    {
        Http::fakeSequence()->push($this->response('18000000'))
            ->pushFailedConnection('balance-test-secret')->push($this->response('19000000'));
        $service = app(YdPayBalanceService::class);
        $initial = $service->getBalance();
        $this->travel(301)->seconds();
        $failed = $service->getBalance();
        $this->assertSame('180000.00', $failed['balance']);
        $this->assertSame($initial['fetched_at'], $failed['fetched_at']);
        $this->assertTrue($failed['is_stale']);
        $this->assertFalse($failed['is_available']);
        $this->assertSame('180000.00', $service->getBalance()['balance']);
        $this->travel(31)->seconds();
        $this->assertSame('190000.00', $service->getBalance()['balance']);
    }

    public function test_cache_and_manual_cooldown_limit_provider_calls(): void
    {
        Http::fakeSequence()->push($this->response('10000'))->push($this->response('20000'))->push($this->response('30000'));
        $service = app(YdPayBalanceService::class);
        $this->assertNull($service->getCachedBalance()['balance']);
        Http::assertNothingSent();
        $this->assertSame('100.00', $service->getBalance()['balance']);
        $this->assertSame('100.00', $service->getBalance()['balance']);
        $this->assertSame('200.00', $service->refresh()['balance']);
        $this->assertSame('200.00', $service->refresh()['balance']);
        $this->travel(301)->seconds();
        $this->assertSame('300.00', $service->getBalance()['balance']);
        Http::assertSentCount(3);
    }

    public function test_overlapping_refresh_cannot_query_provider_twice(): void
    {
        $lock = Cache::lock(YdPayBalanceService::REFRESH_LOCK_KEY, 30);
        $this->assertTrue($lock->get());
        try {
            $this->assertNull(app(YdPayBalanceService::class)->refresh()['balance']);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_disabled_or_missing_configuration_does_not_query_provider(): void
    {
        config(['services.ydpay.enabled' => false]);
        $service = app(YdPayBalanceService::class);
        $this->assertSame('disabled', $service->getBalance()['health']);
        config(['services.ydpay.enabled' => true, 'services.ydpay.secret' => null]);
        $this->assertSame('configuration_incomplete', $service->refresh()['health']);
        Http::assertNothingSent();
    }

    public function test_topbar_load_and_refresh_use_ydpay_without_exposing_credentials_or_qc168_balance(): void
    {
        Cache::put(Qc168BalanceService::LAST_SUCCESS_KEY, ['balance' => '999999.00', 'currency' => 'vnd'], 300);
        Http::fakeSequence()->push($this->response('12345600'))->push($this->response('23456700'));
        $component = Livewire::test('admin-payment-gateway-balance-indicator');
        Http::assertNothingSent();
        $component->call('loadBalance')->assertSee('YDPAY Available Payout Balance')->assertSee('₫123,456')
            ->assertDontSee('QC168 Balance')->assertDontSee('999,999')->assertDontSee('balance-test-secret')->assertDontSee('proxy.example');
        $component->call('refreshBalance')->assertSee('₫234,567');
        Http::assertSentCount(2);

        app(PanelRegistry::class)->get('admin')->boot();
        $html = FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE)->toHtml();
        $this->assertStringContainsString('data-balance-provider="ydpay"', $html);
        $this->assertStringNotContainsString('data-balance-provider="qc168"', $html);
    }

    private function response(mixed $available): array
    {
        return $this->signed(['retCode' => '0', 'agentpayBalance' => '999999999999999999999', 'availableAgentpayBalance' => $available]);
    }

    private function signed(array $data): array
    {
        $data['sign'] = app(YdPayGateway::class)->sign($data);

        return $data;
    }
}
