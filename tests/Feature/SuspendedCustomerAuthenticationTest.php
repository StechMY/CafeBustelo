<?php

namespace Tests\Feature;

use App\Http\Middleware\HumanOnly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SuspendedCustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();
        $this->withoutMiddleware(HumanOnly::class);
        Http::fake();
    }

    public function test_suspended_customer_cannot_log_in_on_web_or_api(): void
    {
        $user = $this->customer('suspended');

        $this->post('/login', [
            'username' => $user->username, 'password' => 'password', 'remember' => true,
        ])->assertSessionHasErrors(['username' => __('auth.account_suspended')]);
        $this->assertGuest('web');

        $this->postJson('/api/v1/login', [
            'username' => $user->username, 'password' => 'password',
        ])->assertUnauthorized()->assertJsonPath('message', __('auth.account_suspended'));

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->loginLogs()->count());
        $this->assertNull($user->fresh()->last_login_at);
    }

    public function test_active_and_inactive_customers_can_still_log_in(): void
    {
        foreach (['active', 'inactive'] as $status) {
            $user = $this->customer($status);
            $this->post('/login', [
                'username' => $user->username, 'password' => 'password',
            ])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user, 'web');
            Auth::guard('web')->logout();
            Auth::forgetGuards();

            $this->postJson('/api/v1/login', [
                'username' => $user->username, 'password' => 'password',
            ])->assertOk()->assertJsonStructure(['data' => ['access_token']]);
        }
    }

    public function test_incorrect_password_does_not_show_a_suspension_message(): void
    {
        $user = $this->customer('suspended');

        $this->post('/login', [
            'username' => $user->username, 'password' => 'wrong-password',
        ])->assertSessionHasErrors(['username' => 'Invalid username or password.']);
        $this->assertGuest('web');
    }

    public function test_suspension_revokes_tokens_and_remember_me_credentials(): void
    {
        $user = $this->customer('active');
        $user->forceFill(['remember_token' => 'old-remember-token'])->save();
        $token = $user->createToken('phone')->plainTextToken;
        $user->createToken('tablet');

        $user->update(['status' => 'suspended']);

        $this->assertNull($user->fresh()->remember_token);
        $this->assertSame(0, $user->tokens()->count());
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();

        $user->update(['status' => 'active']);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/login', [
            'username' => $user->username, 'password' => 'password',
        ])->assertOk();
    }

    public function test_existing_web_session_is_logged_out_on_the_next_request(): void
    {
        $user = $this->customer('active');
        $sessionKey = Auth::guard('web')->getName();
        $this->withSession([$sessionKey => $user->id, 'private_session_data' => 'test']);
        $user->update(['status' => 'suspended']);
        Auth::forgetGuards();

        $this->get('/dashboard')->assertRedirect(route('login'))
            ->assertSessionHasErrors('username')
            ->assertSessionMissing($sessionKey)
            ->assertSessionMissing('private_session_data');
        $this->assertGuest('web');
    }

    public function test_existing_suspended_customer_is_logged_out_when_opening_login(): void
    {
        $user = $this->customer('suspended');
        $this->withSession([Auth::guard('web')->getName() => $user->id]);
        Auth::forgetGuards();

        $this->get('/login')->assertRedirect(route('login'))->assertSessionHasErrors('username');
        $this->assertGuest('web');
    }

    public function test_legacy_suspended_token_is_rejected_and_revoked(): void
    {
        $user = $this->customer('active');
        $token = $user->createToken('legacy-phone')->plainTextToken;
        DB::table('users')->where('id', $user->id)->update(['status' => 'suspended']);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertUnauthorized()->assertJsonPath('message', __('auth.account_suspended'));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_legacy_remember_me_cookie_cannot_restore_a_suspended_session(): void
    {
        $user = $this->customer('active');
        $user->forceFill(['remember_token' => 'legacy-cookie-token'])->save();
        $cookieName = Auth::guard('web')->getRecallerName();
        $cookieValue = $user->id.'|legacy-cookie-token|'.$user->getAuthPassword();
        DB::table('users')->where('id', $user->id)->update(['status' => 'suspended']);
        Auth::forgetGuards();

        $this->withCookie($cookieName, $cookieValue)->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username')
            ->assertCookieExpired($cookieName);
        $this->assertGuest('web');
    }

    public function test_suspended_session_cannot_send_bank_otp_or_submit_withdrawal(): void
    {
        $user = $this->customer('suspended');

        foreach (['/bank-account/send-otp', '/withdraw'] as $path) {
            $this->withSession([Auth::guard('web')->getName() => $user->id]);
            Auth::forgetGuards();
            $this->post($path)->assertRedirect(route('login'));
            $this->assertGuest('web');
        }

        $this->assertDatabaseCount('withdraw_requests', 0);
        $this->assertDatabaseCount('phone_otps', 0);
    }

    private function customer(string $status): User
    {
        return User::create([
            'name' => 'Authentication Test', 'username' => 'test-'.$status,
            'password' => 'password', 'status' => $status,
        ]);
    }
}
