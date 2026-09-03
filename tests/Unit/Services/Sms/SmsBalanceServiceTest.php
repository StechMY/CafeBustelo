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
            'sms.base_url' => 'http://sms-provider.test',
            'sms.appkey' => 'abc',
            'sms.appcode' => 'app-code',
            'sms.appsecret' => 'secret',
            'sms.balance_cache_seconds' => 300,
            'sms.balance_last_known_seconds' => 86400,
            'sms.balance_refresh_min_interval' => 30,
            'sms.balance_manual_refresh_cooldown' => 10,
            'sms.balance_warning_threshold' => 5000,
            'sms.balance_critical_threshold' => 1000,
        ]);

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
            $this->assertStringStartsWith('http://sms-provider.test/sms/balance/v1?', $request->url());
            $this->assertSame('abc', $request['appkey']);
            $this->assertSame('app-code', $request['appcode']);
            $this->assertSame('1234567890123', $request['timestamp']);
            $this->assertSame(md5('abcsecret1234567890123'), $request['sign']);

            return Http::response([
                'appkey' => 'test',
                'balance' => '12580',
                'balance_time' => '2026-09-03 15:48:00',
            ]);
        });

        $service = app(SmsBalanceService::class);
        $first = $service->getBalance();
        $second = $service->getBalance();

        $this->assertSame(12580, $first['balance']);
        $this->assertSame('2026-09-03 15:48:00', $first['provider_balance_time']);
        $this->assertTrue($first['is_available']);
        $this->assertFalse($first['is_stale']);
        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_zero_is_a_valid_critical_balance(): void
    {
        Http::fake([
            '*/sms/balance/v1*' => Http::response([
                'balance' => '0',
                'balance_time' => '2026-09-03 15:48:00',
            ]),
        ]);

        $result = app(SmsBalanceService::class)->getBalance();

        $this->assertSame(0, $result['balance']);
        $this->assertTrue($result['is_available']);
        $this->assertSame('critical', $result['health']);
    }

    public function test_provider_error_does_not_overwrite_last_successful_balance(): void
    {
        Http::fakeSequence()
            ->push(['balance' => '12580', 'balance_time' => '2026-09-03 15:48:00'])
            ->push(['code' => 'F0006', 'desc' => 'provider detail']);

        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->copy()->addSeconds(31));

        $result = $service->getBalance();

        $this->assertSame(12580, $result['balance']);
        $this->assertTrue($result['is_stale']);
        $this->assertFalse($result['is_available']);
        $this->assertSame('provider_error', $result['health']);
        $this->assertSame(12580, $service->lastKnownBalance()['balance']);
    }

    public function test_timeout_keeps_last_balance_and_logs_no_credentials(): void
    {
        Http::fake([
            '*/sms/balance/v1*' => Http::response(['balance' => '12580']),
        ]);

        $service = app(SmsBalanceService::class);
        $service->getBalance();
        $service->markStale();
        Carbon::setTestNow(now()->copy()->addSeconds(31));
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('request containing secret failed'));

        $result = $service->getBalance();

        $this->assertSame(12580, $result['balance']);
        $this->assertTrue($result['is_stale']);
        Log::shouldHaveReceived('warning')->with(
            'SMS balance lookup failed.',
            \Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context), 'secret')),
        )->once();
    }

    public function test_refresh_lock_prevents_a_second_provider_request(): void
    {
        Http::fake();
        $lock = Cache::lock(SmsBalanceService::REFRESH_LOCK_KEY, 20);
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
            '*/sms/balance/v1*' => Http::response(['balance' => '12580']),
        ]);

        $service = app(SmsBalanceService::class);
        $service->refresh();
        $service->refresh();

        Http::assertSentCount(1);
    }

    public function test_sms_send_updates_balance_health_centrally(): void
    {
        Http::fakeSequence()
            ->push(['code' => '00000', 'uid' => 'success'])
            ->push(['code' => 'F9999'])
            ->push(['code' => 'F0015']);

        $client = app(SmsClient::class);
        Cache::put(SmsBalanceService::STALE_KEY, false);
        $this->assertTrue($client->send('84912345678', 'test')['success']);
        $this->assertTrue(Cache::get(SmsBalanceService::STALE_KEY));

        Cache::put(SmsBalanceService::STALE_KEY, false);
        Cache::put(SmsBalanceService::HEALTH_KEY, 'healthy');
        $this->assertFalse($client->send('84912345678', 'test')['success']);
        $this->assertFalse(Cache::get(SmsBalanceService::STALE_KEY));
        $this->assertSame('healthy', Cache::get(SmsBalanceService::HEALTH_KEY));

        $this->assertFalse($client->send('84912345678', 'test')['success']);
        $this->assertTrue(Cache::get(SmsBalanceService::STALE_KEY));
        $this->assertSame('insufficient', Cache::get(SmsBalanceService::HEALTH_KEY));
    }

    public function test_header_renders_without_exposing_credentials(): void
    {
        config([
            'sms.appkey' => 'browser-key-must-not-appear',
            'sms.appsecret' => 'browser-secret-must-not-appear',
        ]);
        $signature = md5('browser-key-must-not-appearbrowser-secret-must-not-appear1234567890123');
        Http::fake([
            '*/sms/balance/v1*' => Http::response(['balance' => '12580']),
        ]);

        $html = Livewire::test('admin-sms-balance-indicator')->html();

        $this->assertStringContainsString('12,580', $html);
        $this->assertStringNotContainsString('browser-key-must-not-appear', $html);
        $this->assertStringNotContainsString('browser-secret-must-not-appear', $html);
        $this->assertStringNotContainsString($signature, $html);
    }

    public function test_header_still_renders_when_provider_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        Livewire::test('admin-sms-balance-indicator')
            ->assertSee(__('filament.sms_balance_unavailable'));
    }
}
