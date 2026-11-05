<?php

namespace Tests\Feature;

use App\Filament\Resources\RechargeRequests\Pages\ListRechargeRequests;
use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use App\Models\AdminUser;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Models\WithdrawRequest;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RequestListSummaryTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    private User $player;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO is required.');
        }
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        parent::setUp();
        Carbon::setTestNow('2026-10-22 15:00:00');
        Http::fake();
        app()->setLocale('en');
        $this->player = $this->user('visible-player');
        $this->admin = AdminUser::create([
            'username' => '1002', 'password' => 'password', 'status' => 'active',
        ]);
        foreach (['ViewAny:RechargeRequest', 'View:RechargeRequest', 'ViewAny:WithdrawRequest', 'View:WithdrawRequest'] as $permission) {
            $this->admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public static function lists(): array
    {
        return [
            'deposits' => [RechargeRequest::class, ListRechargeRequests::class],
            'withdrawals' => [WithdrawRequest::class, ListWithdrawRequests::class],
        ];
    }

    #[DataProvider('lists')]
    public function test_approval_dates_and_totals_include_all_pages_and_respect_search_and_scope(string $model, string $page): void
    {
        $root = $this->user('managed-root');
        $this->player->update(['referrer_id' => $root->id]);
        $outside = $this->user('outside-player');
        $this->admin->update(['managed_user_id' => $root->id]);
        $this->admin->assignRole(Role::findOrCreate('Support', 'admin'));

        $first = $this->record($model, ['reviewed_at' => '2026-10-22 00:00:00']);
        $last = $this->record($model, ['reviewed_at' => '2026-10-22 23:59:59']);
        for ($i = 0; $i < 24; $i++) {
            $this->record($model);
        }
        $previous = $this->record($model, ['reviewed_at' => '2026-10-21 23:59:59', 'created_at' => now()]);
        $next = $this->record($model, ['reviewed_at' => '2026-10-23 00:00:00']);
        $rejected = $this->record($model, ['status' => 'rejected']);
        $pending = $this->record($model, ['status' => 'pending', 'reviewed_at' => null]);
        $hidden = $this->record($model, ['user_id' => $outside->id, 'amount' => 9000000]);
        $this->record($model, ['user_id' => $root->id, 'amount' => 8000000]);

        $component = Livewire::test($page)
            ->assertSee(__('request-summary.approval_date'))
            ->filterTable('approved_at', ['from' => '2026-10-22', 'until' => '2026-10-22'])
            ->set('tableRecordsPerPage', 10)
            ->assertCountTableRecords(26)
            ->assertCanNotSeeTableRecords([$previous, $next, $rejected, $pending, $hidden]);
        $this->assertContains($first->id, $component->instance()->getFilteredTableQuery()->pluck('id')->all());
        $this->assertContains($last->id, $component->instance()->getFilteredTableQuery()->pluck('id')->all());
        $this->assertSummary($component, 26, 2600.26, $model);
        $component->call('gotoPage', 2);
        $this->assertSummary($component, 26, 2600.26, $model);

        $component->searchTable('no-matching-player')->assertCountTableRecords(0);
        $this->assertSummary($component, 0, 0, $model);
        $component->searchTable('visible-player')->assertCountTableRecords(26);
        $this->assertSummary($component, 26, 2600.26, $model);

        $component->removeTableFilter('approved_at')->assertCountTableRecords(30);
        $this->assertSummary($component, 30, 3000.30, $model);
        $component->filterTable('status', 'rejected')->assertCountTableRecords(1);
        $this->assertSummary($component, 1, 100.01, $model);
    }

    public function test_withdrawal_remarks_can_be_viewed_filtered_and_searched(): void
    {
        $remark = "多付登记 REF-731\n<em>已核对</em>";
        $noted = $this->record(WithdrawRequest::class, ['admin_remark' => $remark]);
        $other = $this->record(WithdrawRequest::class, ['admin_remark' => 'Other reference']);
        $blank = $this->record(WithdrawRequest::class, ['admin_remark' => " \t\r\n"]);
        $empty = $this->record(WithdrawRequest::class);

        Livewire::test(ListWithdrawRequests::class)
            ->assertSeeHtml('data-fi-modal-id="withdraw-remark-'.$noted->id.'"')
            ->assertDontSeeHtml('data-fi-modal-id="withdraw-remark-'.$blank->id.'"')
            ->assertDontSeeHtml('data-fi-modal-id="withdraw-remark-'.$empty->id.'"')
            ->assertSee($remark)
            ->assertDontSeeHtml('<em>已核对</em>')
            ->filterTable('has_admin_remark')
            ->assertCanSeeTableRecords([$noted, $other])
            ->assertCountTableRecords(2)
            ->searchTable('REF-731')
            ->assertCanSeeTableRecords([$noted])
            ->assertCountTableRecords(1);
    }

    public function test_withdrawal_summary_can_be_limited_to_completed_payouts(): void
    {
        $this->record(WithdrawRequest::class);
        $processing = $this->record(WithdrawRequest::class, ['payout_status' => 'processing']);
        $component = Livewire::test(ListWithdrawRequests::class)
            ->filterTable('approved_at', ['from' => '2026-10-22', 'until' => '2026-10-22']);
        $this->assertSummary($component, 2, 200.02, WithdrawRequest::class);
        $component->filterTable('payout_status', 'completed')->assertCanNotSeeTableRecords([$processing]);
        $this->assertSummary($component, 1, 100.01, WithdrawRequest::class);
    }

    #[DataProvider('lists')]
    public function test_open_ended_approval_dates(string $model, string $page): void
    {
        $this->record($model, ['reviewed_at' => '2026-10-21 23:59:59']);
        $this->record($model);
        $this->record($model, ['reviewed_at' => '2026-10-23 00:00:00']);
        $this->record($model, ['status' => 'rejected']);
        $component = Livewire::test($page)
            ->filterTable('approved_at', ['from' => '2026-10-22', 'until' => null])
            ->assertCountTableRecords(2);
        $this->assertSummary($component, 2, 200.02, $model);
        $component->filterTable('approved_at', ['from' => null, 'until' => '2026-10-22'])
            ->assertCountTableRecords(2);
        $this->assertSummary($component, 2, 200.02, $model);
    }

    public function test_paid_statistics_dates_use_completion_then_review_time_and_include_legacy_paid_records(): void
    {
        $gateway = $this->record(WithdrawRequest::class, [
            'reviewed_at' => '2026-10-20 12:00:00', 'gateway_provider' => 'qc168',
            'gateway_completed_at' => '2026-10-22 00:00:00',
        ]);
        $manual = $this->record(WithdrawRequest::class, ['reviewed_at' => '2026-10-22 23:59:59']);
        $legacy = $this->record(WithdrawRequest::class, ['payout_status' => null, 'gateway_provider' => null]);
        $yesterday = $this->record(WithdrawRequest::class, ['gateway_completed_at' => '2026-10-21 23:59:59']);
        $tomorrow = $this->record(WithdrawRequest::class, ['gateway_completed_at' => '2026-10-23 00:00:00']);
        $processing = $this->record(WithdrawRequest::class, ['payout_status' => 'processing']);
        $unknownDate = $this->record(WithdrawRequest::class, ['reviewed_at' => null]);
        $component = Livewire::test(ListWithdrawRequests::class)
            ->filterTable('paid_at', ['from' => '2026-10-22', 'until' => '2026-10-22'])
            ->assertCanSeeTableRecords([$gateway, $manual, $legacy])
            ->assertCanNotSeeTableRecords([$yesterday, $tomorrow, $processing, $unknownDate])
            ->assertCountTableRecords(3);
        $this->assertSummary($component, 3, 300.03, WithdrawRequest::class);
        $component->filterTable('paid_at', ['from' => null, 'until' => '2026-10-22'])
            ->assertCountTableRecords(4);
        $component->filterTable('paid_at', ['from' => '2026-10-22', 'until' => null])
            ->assertCountTableRecords(4);
        $component->removeTableFilter('paid_at')->assertCountTableRecords(7);
    }

    public function test_dashboard_total_filter_excludes_virtual_and_unpaid_records_without_expanding_support_scope(): void
    {
        $root = $this->user('root');
        $this->player->update(['referrer_id' => $root->id]);
        $this->admin->update(['managed_user_id' => $root->id]);
        $this->admin->assignRole(Role::findOrCreate('Support', 'admin'));
        $virtual = $this->user('virtual-player');
        $virtual->update(['referrer_id' => $root->id, 'is_virtual' => true]);
        $outside = $this->user('outside');
        $paid = $this->record(WithdrawRequest::class);
        $legacy = $this->record(WithdrawRequest::class, ['payout_status' => null]);
        $this->record(WithdrawRequest::class, ['user_id' => $virtual->id]);
        $this->record(WithdrawRequest::class, ['user_id' => $outside->id]);
        $this->record(WithdrawRequest::class, ['payout_status' => 'failed']);
        $this->record(WithdrawRequest::class, ['payout_status' => 'cancelled', 'status' => 'rejected']);
        $component = Livewire::test(ListWithdrawRequests::class)
            ->filterTable('dashboard_paid')
            ->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$paid, $legacy]);
        $this->assertSummary($component, 2, 200.02, WithdrawRequest::class);
        $component->filterTable('paid_at', ['from' => '2026-10-22', 'until' => '2026-10-22'])
            ->assertCountTableRecords(2);
        $this->assertSummary($component, 2, 200.02, WithdrawRequest::class);
        $component->removeTableFilter('dashboard_paid')->assertCountTableRecords(3);
    }

    private function assertSummary($component, int $count, float $amount, string $model): void
    {
        $summary = $component->instance()->getTable()->getHeader()->getData()['summary'];
        $this->assertSame($count, (int) $summary->records);
        $this->assertEqualsWithDelta($amount, $summary->amount, 0.001);
        $component->assertSee('Filtered totals')->assertSee(number_format($amount, 2));
        if ($model === WithdrawRequest::class) {
            $this->assertEqualsWithDelta($count * 7.01, $summary->fee, 0.001);
            $this->assertEqualsWithDelta($count * 93, $summary->net, 0.001);
        } else {
            $this->assertFalse(property_exists($summary, 'fee'));
        }
    }

    private function user(string $name): User
    {
        return User::create([
            'username' => $name, 'name' => $name, 'password' => 'password', 'status' => 'active',
        ]);
    }

    private function record(string $model, array $attributes = []): Model
    {
        return $model::forceCreate(array_merge([
            'user_id' => $this->player->id, 'amount' => 100.01, 'status' => 'approved',
            'reviewed_at' => '2026-10-22 12:00:00', 'reviewed_by' => $this->admin->id,
            'created_at' => '2026-10-21 10:00:00',
        ], $model === WithdrawRequest::class ? [
            'fee_amount' => 7.01, 'net_amount' => 93,
            'approval_mode' => 'manual', 'payout_status' => 'completed',
        ] : ['deposit_method' => 'manual'], $attributes));
    }
}
