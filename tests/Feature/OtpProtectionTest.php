<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OtpProtectionTest extends TestCase
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
            'turnstile.site_key' => 'public-test-site',
            'turnstile.secret_key' => 'private-test-secret',
            'turnstile.hostnames' => ['otp.test'],
            'trustedproxy.proxies' => [],
        ]);
        (require database_path('migrations/2026_06_25_132549_create_phone_otps_table.php'))->up();
        (require database_path('migrations/2026_07_11_160534_add_code_encrypted_to_phone_otps_table.php'))->up();
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('phone');
        });
        Schema::create('user_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status');
        });
        Http::preventStrayRequests();
        Log::spy();
        Log::shouldReceive('channel')->with('otp')->andReturnSelf();
    }

    public static function webRoutes(): array
    {
        return [
            ['register.otp.send', 'register'],
            ['bank-account.otp.send', 'bank_account'],
            ['settings.password.otp.send', 'change_password'],
        ];
    }

    private function loginFor(string $purpose, int $id = 1): void
    {
        if ($purpose !== 'register') {
            $this->actingAs((new User)->forceFill(['id' => $id, 'phone' => '84912345678']));
        }
    }

    private function fakeProvider(string $purpose = 'register', array $overrides = []): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(array_merge([
                'success' => true, 'hostname' => 'otp.test', 'action' => 'otp_'.$purpose,
            ], $overrides)),
            'sms-provider.test/*' => Http::response(['code' => 200, 'data' => ['sendCode' => 'test-batch']]),
        ]);
    }

    #[DataProvider('webRoutes')]
    #[DataProvider('appRoutes')]
    public function test_each_send_endpoint_rejects_requests_without_challenge(string $route, string $purpose): void
    {
        $this->loginFor($purpose);
        Http::fake();
        $this->postJson(route($route), ['phone' => '0912345678'])
            ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('phone_otps')->count());
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'otp.send'
            && $context['message_content_redacted'] === null && $context['sms_attempted_at'] === null);
    }

    #[DataProvider('webRoutes')]
    #[DataProvider('appRoutes')]
    public function test_each_endpoint_sends_after_valid_challenge(string $route, string $purpose): void
    {
        $this->loginFor($purpose);
        $this->fakeProvider($purpose);
        $response = $this->postJson(route($route), ['phone' => '0912345678', 'turnstile_token' => 'one-use-token']);
        $route === 'settings.password.otp.send' ? $response->assertRedirect() : $response->assertOk();
        Http::assertSentCount(2);
        $this->assertSame($purpose, DB::table('phone_otps')->value('purpose'));
        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context) use ($route): bool {
            $serialized = json_encode($context);

            return $message === 'otp.send' && $context['result'] === 'submitted'
                && $context['sms_uid'] === 'test-batch'
                && $context['entry_point'] === (str_starts_with($route, 'api.') ? 'api' : 'web')
                && $context['sms_provider'] === 'abosend'
                && ! str_contains($serialized, 'one-use-token')
                && ! str_contains($serialized, 'private-test-secret')
                && ! str_contains($serialized, '84912345678');
        });
    }

    public static function messageLocales(): array
    {
        return [['en'], ['vi'], ['zh_CN'], ['id']];
    }

    #[DataProvider('messageLocales')]
    public function test_audit_records_the_actual_outgoing_message_with_otp_redacted(string $locale): void
    {
        app()->setLocale($locale);
        $this->fakeProvider();
        $this->postJson(route('api.v1.register.otp.send'), [
            'phone' => '0912345678', 'turnstile_token' => 'valid-token',
        ])->assertOk();
        $sent = Http::recorded(fn ($request) => str_contains($request->url(), 'sms-provider'))->first()[0]['content'];
        $this->assertSame(1, preg_match('/[0-9]{6}/', $sent, $matches));
        $redacted = str_replace($matches[0], '******', $sent);
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'otp.send'
            && $context['message_content_redacted'] === $redacted
            && $context['message_locale'] === $locale
            && $context['sms_uid'] === 'test-batch'
            && $context['purpose'] === 'register'
            && is_string($context['sms_attempted_at'])
            && ! str_contains($context['message_content_redacted'], $matches[0]));
    }

    public function test_snapshot_preserves_prefix_and_spacing_and_does_not_rebuild_a_changed_template(): void
    {
        app()->setLocale('en');
        app('translator')->addLines(['otp.sms_message' => 'Qsms:  :code / :code.'], 'en');
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'otp.test', 'action' => 'otp_register']),
            'sms-provider.test/*' => function ($request) {
                $this->assertMatchesRegularExpression('/^Qsms:  ([0-9]{6}) \/ \1\.$/', $request['content']);
                app('translator')->addLines(['otp.sms_message' => 'New template :code'], 'en');

                return Http::response(['code' => 200, 'data' => ['sendCode' => 'test-batch']]);
            }]);
        $this->postJson(route('api.v1.register.otp.send'), [
            'phone' => '0912345678', 'turnstile_token' => 'valid-token',
        ])->assertOk();
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'otp.send'
            && $context['message_content_redacted'] === 'Qsms:  ****** / ******.');
    }

    public static function submissionFailures(): array
    {
        return [['provider_rejected'], ['provider_uncertain']];
    }

    #[DataProvider('submissionFailures')]
    public function test_failed_submission_still_records_the_redacted_content(string $result): void
    {
        $sent = null;
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true, 'hostname' => 'otp.test', 'action' => 'otp_register']),
            'sms-provider.test/*' => function ($request) use ($result, &$sent) {
                $sent = $request['content'];
                if ($result === 'provider_uncertain') {
                    throw new ConnectionException('timeout');
                }

                return Http::response(['code' => 403]);
            }]);
        $this->postJson(route('api.v1.register.otp.send'), [
            'phone' => '0912345678', 'turnstile_token' => 'valid-token',
        ])->assertUnprocessable();
        $this->assertIsString($sent);
        $this->assertSame(1, preg_match('/[0-9]{6}/', $sent, $matches));
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'otp.send'
            && $context['result'] === $result && $context['sms_uid'] === null
            && $context['message_content_redacted'] === str_replace($matches[0], '******', $sent));
        $this->assertSame(0, DB::table('phone_otps')->count());
    }

    public static function invalidChallenges(): array
    {
        return [[['success' => false]], [['hostname' => 'attacker.test']], [['action' => 'otp_bank_account']]];
    }

    #[DataProvider('invalidChallenges')]
    public function test_invalid_challenge_never_sends_sms(array $overrides): void
    {
        $this->fakeProvider('register', $overrides);
        $this->postJson(route('register.otp.send'), ['phone' => '0912345678', 'turnstile_token' => 'bad-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sms-provider'));
    }

    public function test_challenge_cannot_be_replayed_with_another_phone(): void
    {
        $this->fakeProvider();
        $this->postJson(route('register.otp.send'), ['phone' => '0912345678', 'turnstile_token' => 'same-token'])->assertOk();
        $this->postJson(route('register.otp.send'), ['phone' => '0912345679', 'turnstile_token' => 'same-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        $this->assertSame(1, DB::table('phone_otps')->count());
    }

    public function test_missing_turnstile_configuration_fails_closed(): void
    {
        config(['turnstile.secret_key' => null]);
        Http::fake();
        $this->postJson(route('register.otp.send'), ['phone' => '0912345678', 'turnstile_token' => 'token'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_challenge_outage_fails_closed(): void
    {
        Http::fake(fn () => throw new ConnectionException('secret-must-not-be-logged'));
        $this->postJson(route('register.otp.send'), ['phone' => '0912345678', 'turnstile_token' => 'token'])
            ->assertUnprocessable()->assertJsonMissing(['secret-must-not-be-logged']);
        $this->assertSame(0, DB::table('phone_otps')->count());
    }

    public function test_rejected_form_does_not_flash_challenge_token_into_session(): void
    {
        $this->loginFor('change_password');
        $this->fakeProvider('change_password', ['success' => false]);
        $this->from('/settings/password')->post(route('settings.password.otp.send'), [
            'turnstile_token' => 'private-one-use-token',
        ])->assertRedirect('/settings/password')->assertSessionHasErrors('turnstile_token')
            ->assertSessionMissing('_old_input.turnstile_token');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sms-provider'));
    }

    public function test_widget_exposes_only_public_key_and_expected_action(): void
    {
        view()->share('errors', new ViewErrorBag);
        $html = Blade::render('<x-otp-challenge purpose="register" />');
        $this->assertStringContainsString('public-test-site', $html);
        $this->assertStringContainsString('otp_register', $html);
        $this->assertStringNotContainsString('private-test-secret', $html);
    }

    public static function appRoutes(): array
    {
        return [
            ['api.v1.register.otp.send', 'register'],
            ['api.v1.bank-account.otp.send', 'bank_account'],
            ['api.v1.account.password.otp.send', 'change_password'],
        ];
    }

    #[DataProvider('appRoutes')]
    public function test_app_rejects_wrong_action_and_hostname(string $route, string $purpose): void
    {
        $this->loginFor($purpose);
        foreach ([['action' => 'wrong_action'], ['hostname' => 'attacker.test'], ['success' => false]] as $overrides) {
            $this->fakeProvider($purpose, $overrides);
            $this->postJson(route($route), ['phone' => '0912345678', 'turnstile_token' => 'bad-token'])
                ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sms-provider'));
        }
        $this->assertSame(0, DB::table('phone_otps')->count());
    }

    public function test_web_token_cannot_be_replayed_on_app_api(): void
    {
        $this->fakeProvider();
        $this->postJson(route('register.otp.send'), ['phone' => '0912345678', 'turnstile_token' => 'shared-token'])->assertOk();
        $this->postJson(route('api.v1.register.otp.send'), ['phone' => '0912345679', 'turnstile_token' => 'shared-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('turnstile_token');
        $this->assertSame(1, DB::table('phone_otps')->count());
    }

    #[DataProvider('appRoutes')]
    public function test_app_challenge_page_has_fixed_purpose_and_no_secret(string $route, string $purpose): void
    {
        $this->get(route('otp.app-challenge', ['purpose' => $purpose]))->assertOk()
            ->assertSee('public-test-site')->assertSee('data-purpose="'.$purpose.'"', false)
            ->assertSee('window.OtpChallenge.postMessage', false)->assertDontSee('private-test-secret')
            ->assertHeader('Cache-Control', 'no-store, private');
        Http::assertNothingSent();
    }

    public function test_app_challenge_page_rejects_unknown_purpose(): void
    {
        $this->get('/otp/challenge/arbitrary')->assertNotFound();
    }

    public static function invalidPhones(): array
    {
        return [['4136476'], ['+15551234567'], ['+84212345678'], ['091234567890'], [['0912345678']]];
    }

    #[DataProvider('invalidPhones')]
    public function test_app_rejects_invalid_phone_before_provider_call(mixed $phone): void
    {
        $this->fakeProvider();
        $this->postJson(route('api.v1.register.otp.send'), ['phone' => $phone, 'turnstile_token' => 'valid-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('phone');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sms-provider'));
    }

    public function test_switching_phones_and_forging_forwarded_ip_does_not_bypass_ip_limit(): void
    {
        $this->fakeProvider();
        for ($i = 0; $i < 4; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
                ->withHeader('X-Forwarded-For', '203.0.113.'.($i + 1))
                ->postJson(route('api.v1.register.otp.send'), ['phone' => '091234567'.$i, 'turnstile_token' => 'token-'.$i]);
            $i < 3 ? $response->assertOk() : $response->assertTooManyRequests();
        }
        Http::assertSentCount(6);
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $context['result'] === 'rate_limited'
            && $context['ip'] === '198.51.100.10');
    }

    public function test_trusted_proxy_preserves_distinct_client_ips(): void
    {
        config(['trustedproxy.proxies' => ['192.0.2.10']]);
        $this->fakeProvider();
        for ($i = 0; $i < 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->withHeader('X-Forwarded-For', '203.0.113.'.($i + 1))
                ->postJson(route('api.v1.register.otp.send'), ['phone' => '091234567'.$i, 'turnstile_token' => 'token-'.$i])->assertOk();
        }
        Http::assertSentCount(8);
    }

    public function test_same_user_cannot_evade_limit_by_changing_ip(): void
    {
        $this->loginFor('bank_account');
        $this->fakeProvider('bank_account');
        for ($i = 0; $i < 4; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.($i + 1)])
                ->postJson(route('api.v1.bank-account.otp.send'), ['phone' => '091234567'.$i, 'turnstile_token' => 'token-'.$i]);
            $i < 3 ? $response->assertOk() : $response->assertTooManyRequests();
        }
        Http::assertSentCount(6);
    }
}
