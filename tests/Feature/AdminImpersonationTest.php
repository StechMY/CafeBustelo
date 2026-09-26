<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\AdminUser;
use App\Models\User;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdminImpersonationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'impersonation_test',
            'database.connections.impersonation_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'trustedproxy.proxies' => [],
        ]);
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('status');
            $table->rememberToken();
        });
        DB::table('users')->insert([
            ['id' => 15, 'username' => 'target', 'status' => 'active'],
            ['id' => 16, 'username' => 'other', 'status' => 'active'],
        ]);
        $this->withSession(['admin_impersonation_nonce' => [7 => 'test-session-nonce']]);
    }

    private function loginAdmin(): void
    {
        $this->actingAs((new AdminUser)->forceFill(['id' => 7, 'status' => 'active']), 'admin');
    }

    private function signedPath(bool $temporary = false): string
    {
        return URL::signedRoute(
            'admin.users.impersonate',
            ['user' => 15, 'nonce' => 'test-session-nonce'],
            expiration: $temporary ? now()->addMinutes(2) : null,
            absolute: false,
        );
    }

    public function test_direct_new_tab_link_remains_valid_after_waiting_and_works_behind_https_proxy(): void
    {
        $this->loginAdmin();
        URL::forceScheme('https');
        $table = UsersTable::configure(Table::make($this->createMock(HasTable::class)));
        $action = collect($table->getRecordActions())->first(fn ($action) => $action->getName() === 'login_as_user');
        $this->assertNotNull($action);
        $action->record(User::findOrFail(15));
        $this->assertFalse($action->isConfirmationRequired());
        $this->assertTrue($action->shouldOpenUrlInNewTab());
        $this->assertNull($action->getActionFunction());
        session()->forget('admin_impersonation_nonce');
        $path = $action->getUrl();
        $this->assertSame($path, $action->getUrl());
        $parts = parse_url($path);
        parse_str($parts['query'], $query);
        $this->assertArrayNotHasKey('expires', $query);
        $this->assertSame(32, strlen($query['nonce']));
        $this->assertSame(session('admin_impersonation_nonce.7'), $query['nonce']);
        $this->travel(5)->minutes();

        // TLS terminates at the proxy, while Laravel receives HTTP.
        $this->withHeader('X-Forwarded-Proto', 'https')
            ->get('http://localhost'.$parts['path'].'?'.$parts['query'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('impersonated_by_admin_id', 7)
            ->assertSessionHas('impersonated_user_id', 15);
        $this->assertAuthenticatedAs(User::findOrFail(15), 'web');
        // Logging in a player regenerates the session ID, but should not break other table links.
        $this->get($path)->assertRedirect(route('dashboard'));
    }

    public function test_expired_signature_is_rejected(): void
    {
        $this->loginAdmin();
        $path = $this->signedPath(temporary: true);
        $this->travel(3)->minutes();
        $this->get($path)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_changing_target_user_is_rejected(): void
    {
        $this->loginAdmin();
        $this->get(str_replace('/users/15/', '/users/16/', $this->signedPath()))->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_changing_expiration_is_rejected(): void
    {
        $this->loginAdmin();
        $path = preg_replace('/expires=\d+/', 'expires='.now()->addDay()->timestamp, $this->signedPath(temporary: true));
        $this->get($path)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_unsigned_link_is_rejected_even_for_admin(): void
    {
        $this->loginAdmin();
        $this->get('/admin/users/15/impersonate')->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_valid_signature_does_not_replace_admin_authentication(): void
    {
        $this->get($this->signedPath())->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_link_cannot_be_used_in_another_admin_session(): void
    {
        $this->loginAdmin();
        $path = $this->signedPath();
        $this->withSession(['admin_impersonation_nonce' => [7 => 'another-session-nonce']]);
        $this->get($path)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_link_from_previous_session_is_rejected_after_relogin(): void
    {
        $this->loginAdmin();
        $path = $this->signedPath();
        session()->invalidate();
        $this->loginAdmin();
        $this->get($path)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_link_is_bound_to_the_admin_who_created_it(): void
    {
        $path = $this->signedPath();
        $this->actingAs((new AdminUser)->forceFill(['id' => 8, 'status' => 'active']), 'admin');
        $this->get($path)->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_valid_signature_without_session_nonce_is_rejected(): void
    {
        $this->loginAdmin();
        $this->get(URL::signedRoute('admin.users.impersonate', ['user' => 15], absolute: false))->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_customer_session_cannot_impersonate_another_customer(): void
    {
        $this->actingAs(User::findOrFail(16), 'web');
        $this->get($this->signedPath())->assertForbidden();
        $this->assertAuthenticatedAs(User::findOrFail(16), 'web');
    }

    public function test_suspended_user_cannot_be_impersonated(): void
    {
        $this->loginAdmin();
        DB::table('users')->where('id', 15)->update(['status' => 'suspended']);
        $this->from('/admin/users')->get($this->signedPath())
            ->assertRedirect('/admin/users')->assertSessionHas('error');
        $this->assertGuest('web');
    }
}
