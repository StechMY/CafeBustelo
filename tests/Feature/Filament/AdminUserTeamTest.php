<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdminUsers\Pages\ListAdminUsers;
use App\Models\AdminUser;
use App\Models\RechargeRequest;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PDO;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserTeamTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is unavailable.');
        }

        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();
        app()->setLocale('en');
        Http::fake(fn () => Http::response([], 503));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_tree_keeps_unpaid_parents_and_shows_the_first_approved_recharge(): void
    {
        $root = $this->user();
        $unpaid = $this->user($root);
        $paid = $this->user($unpaid);
        $outsider = $this->user();
        $this->recharge($unpaid, 'pending', 900);
        $this->recharge($unpaid, 'rejected', 800);
        $this->recharge($unpaid, 'reversed', 700);
        $this->recharge($paid, 'approved', 222, '2026-10-20 09:00:00');
        $this->recharge($paid, 'approved', 111, '2026-10-19 09:00:00');
        $this->recharge($paid, 'approved', 333, '2026-10-19 09:00:00');
        $target = $this->admin($root);
        $this->login();

        $component = Livewire::test(ListAdminUsers::class)
            ->mountAction(TestAction::make('viewTeam')->table($target))
            ->assertMountedActionModalSee('2 downline members')->assertMountedActionModalSee('1 recharged members')
            ->assertMountedActionModalSee($unpaid->username)->assertMountedActionModalSee($paid->username)
            ->assertMountedActionModalSee('Not recharged')->assertMountedActionModalSee('Recharged')
            ->assertMountedActionModalSee('111.00')->assertMountedActionModalDontSee('222.00')->assertMountedActionModalDontSee('333.00')
            ->assertMountedActionModalSee('2026-10-19 09:00:00')->assertMountedActionModalDontSee($outsider->username);

        $html = $component->getMountedActionModalHtml();
        $this->assertLessThan(strpos($html, 'data-team-user="'.$paid->id.'"'), strpos($html, 'data-team-user="'.$unpaid->id.'"'));
    }

    public function test_all_depths_virtual_and_inactive_members_are_included_and_cycles_stop(): void
    {
        $root = $this->user();
        $parent = $root;
        for ($depth = 1; $depth <= 9; $depth++) {
            $parent = $this->user($parent);
        }
        $parent->updateQuietly(['is_virtual' => true, 'status' => 'inactive']);
        $root->updateQuietly(['referrer_id' => $parent->id]);
        $this->recharge($parent, 'approved', 123, null);
        $target = $this->admin($root);
        $this->login();

        Livewire::test(ListAdminUsers::class)
            ->mountAction(TestAction::make('viewTeam')->table($target))
            ->assertMountedActionModalSee('9 downline members')->assertMountedActionModalSee('Level 9')
            ->assertMountedActionModalSee($parent->username)->assertMountedActionModalSee('Virtual')->assertMountedActionModalSee('123.00');
    }

    public function test_empty_team_and_unbound_admin_are_handled(): void
    {
        $target = $this->admin($this->user());
        $unbound = $this->admin();
        $this->login();

        Livewire::test(ListAdminUsers::class)
            ->assertActionHidden(TestAction::make('viewTeam')->table($unbound))
            ->mountAction(TestAction::make('viewTeam')->table($target))
            ->assertMountedActionModalSee('No downline members yet.')->assertMountedActionModalSee('0 downline members');
    }

    public function test_user_and_admin_view_permissions_are_required_even_for_forged_actions(): void
    {
        $root = $this->user();
        $child = $this->user($root);
        $target = $this->admin($root);
        $actor = $this->login();

        foreach (['View:AdminUser', 'ViewAny:User', 'View:User'] as $permission) {
            $actor->revokePermissionTo($permission);
            Livewire::test(ListAdminUsers::class)
                ->assertActionHidden(TestAction::make('viewTeam')->table($target))
                ->mountAction(TestAction::make('viewTeam')->table($target))
                ->assertActionNotMounted('viewTeam')
                ->assertDontSee($child->username);
            $actor->givePermissionTo($permission);
        }
    }

    public function test_support_cannot_open_another_admins_team(): void
    {
        $ownRoot = $this->user();
        $ownChild = $this->user($ownRoot);
        $otherRoot = $this->user();
        $otherChild = $this->user($otherRoot);
        $target = $this->admin($otherRoot);
        $actor = $this->login($ownRoot);
        $actor->assignRole(Role::findOrCreate('Support', 'admin'));

        Livewire::test(ListAdminUsers::class)
            ->assertActionHidden(TestAction::make('viewTeam')->table($target))
            ->mountAction(TestAction::make('viewTeam')->table($target))
            ->assertActionNotMounted('viewTeam')->assertDontSee($otherChild->username)
            ->mountAction(TestAction::make('viewTeam')->table($actor))
            ->assertMountedActionModalSee($ownChild->username)->assertMountedActionModalDontSee($otherChild->username);
    }

    private function user(?User $referrer = null): User
    {
        $number = ++$this->sequence;

        return User::withoutEvents(fn () => User::create([
            'username' => 'team-member-'.$number, 'name' => 'Team Member '.$number,
            'password' => 'password', 'referral_code' => 'TEAM'.$number,
            'referrer_id' => $referrer?->id, 'status' => 'active', 'is_virtual' => false,
        ]));
    }

    private function admin(?User $root = null): AdminUser
    {
        return AdminUser::create([
            'username' => 'team-admin-'.++$this->sequence, 'password' => 'password',
            'managed_user_id' => $root?->id, 'status' => 'active',
        ]);
    }

    private function login(?User $root = null): AdminUser
    {
        $admin = $this->admin($root);
        foreach (['ViewAny:AdminUser', 'View:AdminUser', 'ViewAny:User', 'View:User'] as $name) {
            $admin->givePermissionTo(Permission::findOrCreate($name, 'admin'));
        }
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function recharge(User $user, string $status, int $amount, ?string $reviewedAt = '2026-10-18 09:00:00'): void
    {
        RechargeRequest::create([
            'user_id' => $user->id, 'status' => $status, 'amount' => $amount,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
