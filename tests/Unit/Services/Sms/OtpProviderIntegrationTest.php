<?php

namespace Tests\Unit\Services\Sms;

use App\Services\OtpService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OtpProviderIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'otp_test',
            'database.connections.otp_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
            'sms.enabled' => true,
            'sms.base_url' => 'https://sms-provider.test',
            'sms.org_code' => 'test-org',
            'sms.md5_key' => 'test-secret',
            'sms.otp_expire_minutes' => 5,
            'sms.otp_resend_seconds' => 60,
            'sms.otp_max_attempts' => 5,
        ]);
        Http::preventStrayRequests();
        (require database_path('migrations/2026_06_25_132549_create_phone_otps_table.php'))->up();
        (require database_path('migrations/2026_07_11_160534_add_code_encrypted_to_phone_otps_table.php'))->up();
    }

    public static function purposes(): array
    {
        return [['register'], ['bank_account'], ['change_password']];
    }

    #[DataProvider('purposes')]
    public function test_accepted_sms_stores_a_verifiable_otp_for_each_purpose(string $purpose): void
    {
        Http::fake(fn () => Http::response(['code' => 200, 'data' => ['sendCode' => 'abosend-batch']]));
        $service = app(OtpService::class);
        $service->send('0912345678', $purpose);
        $otp = DB::table('phone_otps')->first();
        $code = Crypt::decryptString($otp->code_encrypted);
        $this->assertSame('84912345678', $otp->phone);
        $this->assertSame($purpose, $otp->purpose);
        $this->assertSame('abosend-batch', $otp->sms_uid);
        $this->assertSame('200', $otp->sms_code);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $request = Http::recorded()[0][0];
        $this->assertStringContainsString($code, $request['content']);

        try {
            $service->send('0912345678', $purpose);
            $this->fail('Expected resend cooldown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }
        Http::assertSentCount(1);
        $service->verify('0912345678', $purpose, $code);
        $this->assertNotNull(DB::table('phone_otps')->value('verified_at'));
        try {
            $service->verify('0912345678', $purpose, $code);
            $this->fail('Expected one-time verification.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('otp_code', $exception->errors());
        }
    }

    public function test_rejected_sms_does_not_create_a_valid_otp(): void
    {
        Http::fake(fn () => Http::response(['code' => 403, 'message' => 'provider detail']));
        try {
            app(OtpService::class)->send('0912345678', 'register');
            $this->fail('Expected rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }
        $this->assertSame(0, DB::table('phone_otps')->count());
    }

    public function test_an_unexpired_otp_from_the_old_provider_remains_verifiable(): void
    {
        DB::table('phone_otps')->insert([
            'phone' => '84912345678',
            'purpose' => 'register',
            'code_hash' => Hash::make('123456'),
            'sms_uid' => 'old-provider-batch',
            'sms_code' => '00000',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake();
        app(OtpService::class)->verify('0912345678', 'register', '123456');
        $this->assertNotNull(DB::table('phone_otps')->value('verified_at'));
        Http::assertNothingSent();
    }
}
