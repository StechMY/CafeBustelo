<?php

namespace Tests\Unit\Services\Sms;

use App\Services\Sms\SmsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SmsClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'sms.enabled' => true,
            'sms.base_url' => 'https://sms-provider.test',
            'sms.org_code' => 'test-org',
            'sms.md5_key' => 'test-secret',
            'sms.sender_id' => null,
        ]);
        Http::preventStrayRequests();
    }

    public function test_json_submission_signs_the_exact_unicode_message(): void
    {
        $message = 'Mã xác minh của bạn là 123456. & +';
        Http::fake(function (Request $request) use ($message) {
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://sms-provider.test/v2/api/sendSMS', $request->url());
            $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
            $this->assertSame('84912345678', $request['mobiles']);
            $this->assertSame($message, $request['content']);
            $this->assertSame('test-org', $request['orgCode']);
            $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $request['rand']);
            $this->assertSame(strtoupper(md5('test-org'.$message.$request['rand'].'test-secret')), $request['sign']);
            $this->assertArrayNotHasKey('oaNumber', $request->data());
            $this->assertStringNotContainsString('test-secret', $request->body());

            return Http::response(['code' => 200, 'data' => ['sendCode' => 'batch-123']]);
        });

        $result = app(SmsClient::class)->send('84912345678', $message);
        $this->assertTrue($result['success']);
        $this->assertSame('200', $result['code']);
        $this->assertSame('batch-123', $result['uid']);
        Http::assertSentCount(1);
    }

    public function test_configured_sender_id_is_included(): void
    {
        config(['sms.sender_id' => 'ApprovedSID']);
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['sendCode' => 'batch-1']]));
        app(SmsClient::class)->send('84912345678', 'test');
        Http::assertSent(fn (Request $request) => $request['oaNumber'] === 'ApprovedSID');
    }

    public function test_rejection_and_malformed_success_are_not_accepted(): void
    {
        $sequence = Http::fakeSequence();
        foreach ([
            ['code' => 403, 'message' => 'test-secret'],
            ['code' => '00000', 'uid' => 'old-provider'],
            ['code' => 200],
            ['code' => 200, 'data' => ['sendCode' => '']],
            ['code' => 200, 'data' => ['sendCode' => str_repeat('a', 65)]],
            ['code' => true],
            'not json',
        ] as $response) {
            $sequence->push($response);
            $result = app(SmsClient::class)->send('84912345678', 'test');
            $this->assertFalse($result['success']);
            $this->assertNull($result['uid']);
            $this->assertStringNotContainsString('test-secret', $result['desc']);
        }
    }

    public function test_transport_failure_is_not_retried_or_exposed(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('test-secret 123456 84912345678');
        });
        try {
            app(SmsClient::class)->send('84912345678', '123456');
            $this->fail('Expected a sanitized transport failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('AboSEND request failed. Submission status is unknown.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
        $this->assertSame(1, $attempts);
    }

    public function test_http_failure_is_not_retried(): void
    {
        Http::fake(fn () => Http::response('test-secret', 500));
        try {
            app(SmsClient::class)->send('84912345678', 'test');
            $this->fail('Expected HTTP failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMS HTTP failed: 500', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_missing_credentials_fail_without_sending(): void
    {
        config(['sms.md5_key' => null]);
        Http::fake();
        try {
            app(SmsClient::class)->send('84912345678', 'test');
            $this->fail('Expected configuration failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('AboSEND is not configured.', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_disabled_sms_preserves_local_development_behavior(): void
    {
        config(['sms.enabled' => false]);
        Http::fake();
        $this->assertSame('DISABLED', app(SmsClient::class)->send('84912345678', 'test')['code']);
        Http::assertNothingSent();
    }
}
