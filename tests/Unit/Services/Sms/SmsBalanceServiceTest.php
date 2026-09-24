<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\SmsBalanceService;
use App\Services\Sms\SmsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

class SmsBalanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'sms.enabled' => true,
            'sms.base_url' => 'https://sms-provider.test',
            'sms.org_code' => 'test-org',
            'sms.md5_key' => 'secret',
            'sms.balance_cache_seconds' => 300,
            'sms.balance_last_known_seconds' => 86400,
            'sms.balance_refresh_min_interval' => 30,
            'sms.balance_manual_refresh_cooldown' => 10,
            'sms.balance_warning_threshold' => 5,
            'sms.balance_critical_threshold' => 1,
        ]);

        Http::preventStrayRequests();
        Cache::flush();
        Carbon::setTestNow(Carbon::createFromTimestampMs(1234567890123));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::flush();

        parent::tearDown();
    }

    public function test_balance_signature_is_correct_and_success_is_cached(): void
    {
        Http::fake(function (Request $request) {
            $this->assertSame('https://sms-provider.test/api/viewOrgBalance', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertTrue($request->hasHeader('Content-Type', 'application/x-www-form-urlencoded'));
            $this->assertSame('test-org', $request['orgCode']);
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $request['rand']);
            $this->assertSame(strtoupper(md5('test-org'.$request['rand'].'secret')), $request['sign']);
            $this->assertArrayNotHasKey('md5Key', $request->data());

            return Http::response($this->balanceResponse('5492.1'));
        });

        $service = app(SmsBalanceService::class);
        $first = $service->getBalance();
        $second = $service->getBalance();

        $this->assertSame(5492.1, $first['balance']);
        $this->assertNull($first['provider_balance_time']);
        $this->assertTrue($first['is_available']);
        $this->assertFalse($first['is_stale']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_zero_is_a_valid_critical_balance(): void
    {
        Http::fake([
            '*/api/viewOrgBalance' => Http::response($this->balanceResponse('0')),
        ]);

        $result = app(SmsBalanceService::class)->getBalance();

        $this->assertSame(0.0, $result['balance']);
        $this->assertTrue($result['is_available']);
        $this->assertSame('critical', $result['health']);
    }

    public function test_provider_error_does_not_overwrite_last_successful_balance(): void
    {
        Http::fakeSequence()
            ->push($this->balanceResponse('12580'))
            ->push(['code' => 403, 'message' => 'provider detail']);

        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->copy()->addSeconds(31));

        $result = $service->getBalance();

        $this->assertSame(12580.0, $result['balance']);
        $this->assertTrue($result['is_stale']);
        $this->assertFalse($result['is_available']);
        $this->assertSame('provider_error', $result['health']);
        $this->assertSame(12580.0, $service->lastKnownBalance()['balance']);
    }

    public function test_timeout_keeps_last_balance_and_logs_no_credentials(): void
    {
        Http::fake([
            '*/api/viewOrgBalance' => Http::response($this->balanceResponse('5492.1')),
        ]);

        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->copy()->addSeconds(31));
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('request containing secret failed'));

        $result = $service->getBalance();

        $this->assertSame(5492.1, $result['balance']);
        $this->assertTrue($result['is_stale']);
        Log::shouldHaveReceived('warning')->with(
            'SMS balance lookup failed.',
            \Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context), 'secret')),
        )->once();
    }

    public function test_refresh_lock_prevents_a_second_provider_request(): void
    {
        Http::fake();
        $lock = Cache::lock(app(SmsBalanceService::class)->cacheKey(SmsBalanceService::REFRESH_LOCK_KEY), 20);
        $this->assertTrue($lock->get());

        try {
            $result = app(SmsBalanceService::class)->getBalance();
        } finally {
            $lock->release();
        }

        $this->assertNull($result['balance']);
        Http::assertNothingSent();
    }

    public function test_manual_refresh_has_a_global_cooldown(): void
    {
        Http::fake([
            '*/api/viewOrgBalance' => Http::response($this->balanceResponse('5492.1')),
        ]);

        $service = app(SmsBalanceService::class);
        $service->refresh();
        $service->refresh();

        Http::assertSentCount(1);
    }

    public function test_sms_send_updates_balance_health_centrally(): void
    {
        Http::fakeSequence()
            ->push($this->balanceResponse('3.770000'))
            ->push(['code' => 403])
            ->push(['code' => 200, 'data' => ['sendCode' => 'batch-1']]);

        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $client = app(SmsClient::class);
        $this->assertFalse($client->send('84912345678', 'test')['success']);
        $this->assertSame('provider_error', $service->getBalance()['health']);
        $this->assertTrue($service->isStale());
        $this->assertTrue($client->send('84912345678', 'test')['success']);
        $this->assertTrue($service->isStale());
        $this->assertSame('warning', $service->getBalance()['health']);
        $this->assertSame(3.77, $service->getBalance()['balance']);
        Http::assertSentCount(3);
    }

    public function test_header_renders_without_exposing_credentials(): void
    {
        config([
            'sms.org_code' => 'browser-key-must-not-appear',
            'sms.md5_key' => 'browser-secret-must-not-appear',
        ]);
        Http::fake([
            '*/api/viewOrgBalance' => Http::response($this->balanceResponse('5492.1')),
        ]);

        $component = Livewire::test('admin-sms-balance-indicator');
        Http::assertNothingSent();
        $html = $component->call('loadBalance')->html();

        $this->assertStringContainsString('USD 5,492.1', $html);
        $this->assertStringNotContainsString('browser-key-must-not-appear', $html);
        $this->assertStringNotContainsString('browser-secret-must-not-appear', $html);
        $this->assertStringNotContainsString(Http::recorded()[0][0]['sign'], $html);
    }

    public function test_header_still_renders_when_provider_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        Livewire::test('admin-sms-balance-indicator')
            ->call('loadBalance')
            ->assertSee(__('filament.sms_balance_unavailable'));
    }

    public function test_header_displays_stale_cached_balance_without_waiting_for_provider(): void
    {
        Http::fakeSequence()
            ->push($this->balanceResponse('12.5'))
            ->push($this->balanceResponse('10'));
        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->addSeconds(31));

        $component = Livewire::test('admin-sms-balance-indicator')
            ->assertSet('balance', 12.5)
            ->assertSet('isStale', true)
            ->assertSee('wire:init="loadBalance"', escape: false);
        Http::assertSentCount(1);

        $component->call('loadBalance')
            ->assertSet('balance', 10.0)
            ->assertSet('isStale', false);
        Http::assertSentCount(2);
    }

    public function test_account_and_provider_caches_are_isolated(): void
    {
        Cache::put('sms_provider_balance:last_success', ['balance' => 99999]);
        Http::fake(fn () => Http::response($this->balanceResponse('1.000001')));
        $service = app(SmsBalanceService::class);
        $this->assertSame(1.000001, $service->getBalance()['balance']);

        config(['sms.org_code' => 'another-org']);
        $this->assertNull($service->lastKnownBalance());
        $this->assertSame(1.000001, $service->getBalance()['balance']);
        config(['sms.base_url' => 'https://another-provider.test']);
        $this->assertNull($service->lastKnownBalance());
        $service->getBalance();
        Http::assertSentCount(3);
    }

    public function test_manual_refresh_respects_automatic_refresh_rate_limit(): void
    {
        Http::fake(fn () => Http::response($this->balanceResponse('1')));
        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $service->refresh();
        Http::assertSentCount(1);
        Carbon::setTestNow(now()->addSeconds(10));
        $service->refresh();
        Http::assertSentCount(2);
    }

    public function test_malformed_balance_responses_do_not_erase_the_last_balance(): void
    {
        $service = app(SmsBalanceService::class);
        $sequence = Http::fakeSequence()->push($this->balanceResponse('1'));
        $service->getBalance();
        foreach ([
            ['balance' => '999'],
            ['code' => 200, 'data' => ['balance' => '999', 'orgCode' => 'wrong-org']],
            $this->balanceResponse(null),
            $this->balanceResponse(true),
            $this->balanceResponse('-1'),
            $this->balanceResponse('NaN'),
            $this->balanceResponse([]),
        ] as $response) {
            $sequence->push($response);
            Carbon::setTestNow(now()->addSeconds(31));
            $result = $service->refresh();
            $this->assertSame(1.0, $result['balance']);
            $this->assertFalse($result['is_available']);
            $this->assertTrue($result['is_stale']);
        }
    }

    public function test_missing_credentials_do_not_send_a_request(): void
    {
        config(['sms.md5_key' => '']);
        Http::fake();
        $this->assertSame('CONFIGURATION', app(SmsBalanceService::class)->getBalance()['error_code']);
        Http::assertNothingSent();
    }

    public function test_optional_thresholds_support_fractional_dollar_values(): void
    {
        config(['sms.balance_warning_threshold' => '', 'sms.balance_critical_threshold' => null]);
        Http::fakeSequence()
            ->push($this->balanceResponse('0.25'))
            ->push($this->balanceResponse('0.25'))
            ->push($this->balanceResponse('0.05'));
        $service = app(SmsBalanceService::class);
        $this->assertSame('healthy', $service->getBalance()['health']);
        config(['sms.balance_warning_threshold' => 0.5, 'sms.balance_critical_threshold' => 0.1]);
        Carbon::setTestNow(now()->addSeconds(31));
        $this->assertSame('warning', $service->refresh()['health']);
        Carbon::setTestNow(now()->addSeconds(31));
        $this->assertSame('critical', $service->refresh()['health']);
    }

    private function balanceResponse(mixed $balance): array
    {
        return ['code' => 200, 'data' => ['orgCode' => config('sms.org_code'), 'balance' => $balance]];
    }
}
