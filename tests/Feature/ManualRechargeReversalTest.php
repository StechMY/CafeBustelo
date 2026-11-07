<?php

namespace Tests\Feature;

use App\Filament\Resources\RechargeRequests\Pages\ListRechargeRequests;
use App\Filament\Resources\RechargeRequests\Pages\ViewRechargeRequest;
use App\Models\AdminUser;
use App\Models\PointTransaction;
use App\Models\RechargeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PointService;
use App\Services\RechargeApprovalService;
use App\Services\WalletService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManualRechargeReversalTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    private User $user;

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
        Carbon::setTestNow('2026-10-20 10:00:00');
        app()->setLocale('en');
        Http::fake();
        $this->user = User::create([
            'username' => 'reversal-user', 'name' => 'Reversal User',
            'phone' => '84912345678', 'password' => 'password', 'status' => 'active',
            'accumulated_recharge' => 0,
        ]);
        $this->admin = AdminUser::create([
            'username' => 'reversal-admin', 'password' => 'password', 'status' => 'active',
        ]);
        foreach (['Audit:Recharge', 'ReverseApproval:Recharge', 'ViewAny:RechargeRequest', 'View:RechargeRequest'] as $permission) {
            $this->admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reversal_keeps_credit_adds_audited_debit_and_corrects_both_totals_once(): void
    {
        $request = $this->approved();
        $original = WalletTransaction::firstOrFail();
        $reviewedAt = $request->reviewed_at->toIso8601String();

        $this->reverse($request);

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('0.00', $this->wallet()->balance);
        $this->assertSame('0.00', $this->user->fresh()->accumulated_recharge);
        $this->assertEquals(0, $this->user->fresh()->total_approved_recharge);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertSame('450000.00', $original->fresh()->amount);
        $reversal = WalletTransaction::where('type', 'recharge_reversed')->sole();
        $this->assertSame('debit', $reversal->direction);
        $this->assertSame($request->id, $reversal->reference_id);
        $this->assertSame($original->id, $reversal->meta['original_transaction_id']);
        $this->assertSame($this->admin->id, $reversal->meta['original_reviewed_by']);
        $this->assertSame($reviewedAt, $reversal->meta['original_reviewed_at']);
        $this->assertSame('Initial approval', $reversal->meta['original_admin_remark']);
        $this->assertSame('Duplicate bank receipt', $reversal->remarks);
        $this->assertSame('127.0.0.1', $reversal->meta['ip']);
        $this->assertSame('450000.00', $reversal->balance_before);
        $this->assertSame('0.00', $reversal->balance_after);

        try {
            $this->reverse($request);
            $this->fail('Repeated reversal must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('recharge.reversal_not_approved'), $exception->getMessage());
        }
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertSame('0.00', $this->wallet()->balance);
    }

    public function test_two_wrong_approvals_can_be_reversed_without_removing_the_valid_deposit(): void
    {
        $valid = $this->approved();
        $firstWrong = $this->approved();
        $secondWrong = $this->approved();
        $this->reverse($firstWrong);
        $this->reverse($secondWrong);

        $this->assertTrue($valid->fresh()->isApproved());
        $this->assertSame('450000.00', $this->wallet()->balance);
        $this->assertSame('450000.00', $this->user->fresh()->accumulated_recharge);
        $this->assertEquals(450000, $this->user->fresh()->total_approved_recharge);
        $this->assertDatabaseCount('wallet_transactions', 5);
    }

    #[DataProvider('blockedCases')]
    public function test_unsafe_reversal_leaves_everything_unchanged(string $case, string $error): void
    {
        $request = $this->approved();
        $credit = WalletTransaction::firstOrFail();
        switch ($case) {
            case 'gateway':
                $request->update(['deposit_method' => 'gateway', 'gateway_provider' => 'qc168']);
                break;
            case 'gateway_disguised':
                $request->update(['gateway_payment_uid' => 'gateway-id']);
                break;
            case 'rejected':
                $request->update(['status' => 'rejected']);
                break;
            case 'missing_credit':
                $credit->delete();
                break;
            case 'duplicate_credit':
                $credit->replicate()->save();
                break;
            case 'wrong_amount':
                $credit->update(['amount' => 900000]);
                break;
            case 'changed_balance':
                $this->wallet()->update(['balance' => 900000]);
                break;
            case 'changed_accumulated':
                $this->user->update(['accumulated_recharge' => 100]);
                break;
            case 'spent':
            case 'spent_then_refilled':
                app(WalletService::class)->debit($this->user, Wallet::TYPE_RECHARGE, 'product_purchase', 100);
                if ($case === 'spent_then_refilled') {
                    app(WalletService::class)->credit($this->user, Wallet::TYPE_RECHARGE, 'admin_top_up', 100);
                }
                break;
            case 'locked':
                $this->wallet()->update(['locked_balance' => 1]);
                break;
            case 'points':
                PointTransaction::create([
                    'user_id' => $this->user->id, 'type' => PointTransaction::TYPE_INVITE_DAY_REGISTER,
                    'direction' => 'credit', 'points' => 10, 'balance_before' => 0, 'balance_after' => 10,
                    'meta' => ['recharge_request_id' => $request->id],
                ]);
                break;
        }
        $before = [$request->fresh()->getAttributes(), $this->wallet()->balance,
            $this->user->fresh()->accumulated_recharge, WalletTransaction::count()];
        try {
            $this->reverse($request);
            $this->fail('Unsafe reversal must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__("recharge.{$error}"), $exception->getMessage());
        }
        $this->assertSame($before, [$request->fresh()->getAttributes(), $this->wallet()->balance,
            $this->user->fresh()->accumulated_recharge, WalletTransaction::count()]);
    }

    public static function blockedCases(): array
    {
        return [
            ['gateway', 'manual_only'], ['gateway_disguised', 'manual_only'],
            ['rejected', 'reversal_not_approved'], ['missing_credit', 'reversal_inconsistent'],
            ['duplicate_credit', 'reversal_inconsistent'], ['wrong_amount', 'reversal_inconsistent'],
            ['changed_balance', 'reversal_inconsistent'], ['changed_accumulated', 'reversal_inconsistent'],
            ['spent', 'reversal_insufficient'], ['spent_then_refilled', 'reversal_used'],
            ['locked', 'reversal_used'], ['points', 'reversal_rewards'],
        ];
    }

    public function test_reversal_requires_its_own_permission(): void
    {
        $request = $this->approved();
        $this->admin->revokePermissionTo('ReverseApproval:Recharge');
        try {
            $this->reverse($request);
            $this->fail('Audit permission alone must not authorize reversal.');
        } catch (AuthorizationException) {
            $this->assertTrue($request->fresh()->isApproved());
            $this->assertDatabaseCount('wallet_transactions', 1);
        }
    }

    #[DataProvider('invalidConfirmations')]
    public function test_reversal_requires_matching_amount_and_confirmation(string $amount, bool $confirmed): void
    {
        $request = $this->approved();
        try {
            app(RechargeApprovalService::class)->reverseManualApproval($request, $this->admin, $amount, $confirmed, 'Mistake');
            $this->fail('Invalid confirmation must not reverse the deposit.');
        } catch (ValidationException) {
            $this->assertTrue($request->fresh()->isApproved());
            $this->assertDatabaseCount('wallet_transactions', 1);
        }
    }

    public static function invalidConfirmations(): array
    {
        return [['450001', true], ['450000', false], ['4.5e5', true], ['', true], ['450000.001', true]];
    }

    public function test_reversal_requires_a_reason_and_rechecks_the_locked_amount(): void
    {
        $request = $this->approved();
        foreach ([['450000', true, '   '], ['450000', false, 'mistake'], ['1', true, 'mistake']] as $input) {
            try {
                app(RechargeApprovalService::class)->reverseManualApproval($request, $this->admin, ...$input);
                $this->fail('Invalid reversal must fail.');
            } catch (ValidationException) {
                $this->assertTrue($request->fresh()->isApproved());
                $this->assertDatabaseCount('wallet_transactions', 1);
            }
        }
    }

    public function test_reversal_rolls_back_the_debit_if_updating_the_request_fails(): void
    {
        $request = $this->approved();
        RechargeRequest::updating(function (RechargeRequest $record): void {
            if ($record->status === 'rejected') {
                throw new RuntimeException('Simulated write failure');
            }
        });
        try {
            $this->reverse($request);
            $this->fail('Expected write failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated write failure', $exception->getMessage());
        } finally {
            RechargeRequest::flushEventListeners();
        }
        $this->assertTrue($request->fresh()->isApproved());
        $this->assertSame('450000.00', $this->wallet()->balance);
        $this->assertSame('450000.00', $this->user->fresh()->accumulated_recharge);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_wallet_credit_failure_cannot_leave_a_partially_approved_deposit(): void
    {
        $request = $this->pending();
        $this->mock(WalletService::class)->shouldReceive('credit')
            ->once()->andThrow(new RuntimeException('Wallet write failure'));
        try {
            app(RechargeApprovalService::class)->approve($request, $this->admin);
            $this->fail('Expected wallet failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Wallet write failure', $exception->getMessage());
        }
        $this->assertTrue($request->fresh()->isPending());
        $this->assertSame('0.00', $this->user->fresh()->accumulated_recharge);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_a_legacy_first_deposit_bonus_blocks_automatic_reversal(): void
    {
        $referrer = User::create([
            'username' => 'referrer', 'name' => 'Referrer', 'phone' => '84912345679',
            'password' => 'password', 'status' => 'active',
        ]);
        $this->user->update(['referrer_id' => $referrer->id]);
        foreach ([
            PointService::SETTING_POINTS_ENABLED => true,
            PointService::SETTING_INVITE_DAY_ENABLED => true,
            PointService::SETTING_INVITE_DAY_WEEKDAYS => '[1,2,3,4,5,6,7]',
            PointService::SETTING_INVITE_DAY_AMOUNT => 10,
            PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT => true,
        ] as $key => $value) {
            Setting::setValue($key, $value);
        }
        $request = $this->approved();
        app(PointService::class)->giveInviteDayRegisterPoints($referrer, $this->user, [
            'source' => 'first_approved_recharge',
            'recharge_request_id' => $request->id,
        ]);
        $this->assertSame('10.00', $referrer->fresh()->points_balance);
        try {
            $this->reverse($request);
            $this->fail('Bonus must be reconciled first.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('recharge.reversal_rewards'), $exception->getMessage());
        }
        $this->assertTrue($request->fresh()->isApproved());
        $this->assertSame('450000.00', $this->wallet()->balance);
    }

    public function test_original_detail_approval_without_extra_confirmation_can_still_be_reversed(): void
    {
        $request = $this->pending();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertTrue($request->fresh()->isPending());
        Livewire::test(ViewRechargeRequest::class, ['record' => $request->id])
            ->mountAction('approve')
            ->assertDontSee(__('recharge.confirm_amount'))
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertTrue($request->fresh()->isApproved());
        Livewire::test(ListRechargeRequests::class)
            ->callAction(TestAction::make('reverse_approval')->table($request), data: [
                'confirmed_amount' => '450000', 'confirmed' => true, 'reason' => 'Wrong deposit',
            ])->assertHasNoActionErrors();
        $this->assertTrue($request->fresh()->isRejected());
        Livewire::test(ViewRechargeRequest::class, ['record' => $request->id])
            ->assertActionHidden('reverse_approval')
            ->assertActionHidden('approve');
    }

    public function test_table_approval_and_detail_reversal_keep_the_new_status_visible(): void
    {
        $request = $this->pending();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListRechargeRequests::class)
            ->mountAction(TestAction::make('approve')->table($request))
            ->assertDontSee(__('recharge.confirm_amount'))
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertTrue($request->fresh()->isApproved());
        Livewire::test(ViewRechargeRequest::class, ['record' => $request->id])
            ->callAction('reverse_approval', data: [
                'confirmed_amount' => '450000', 'confirmed' => true, 'reason' => 'Wrong deposit',
            ])->assertHasNoActionErrors()
            ->assertActionHidden('reverse_approval');
        $this->assertTrue($request->fresh()->isRejected());
    }

    public function test_existing_auditor_cannot_see_reversal_and_gateway_deposit_has_no_manual_actions(): void
    {
        $request = $this->approved();
        $this->admin->revokePermissionTo('ReverseApproval:Recharge');
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewRechargeRequest::class, ['record' => $request->id])
            ->assertActionHidden('reverse_approval');
        Livewire::test(ListRechargeRequests::class)
            ->assertActionHidden(TestAction::make('reverse_approval')->table($request));

        $this->admin->givePermissionTo('ReverseApproval:Recharge');
        $request->update(['deposit_method' => 'gateway', 'gateway_provider' => 'qc168']);
        Livewire::test(ViewRechargeRequest::class, ['record' => $request->id])
            ->assertActionHidden('reverse_approval')
            ->assertActionHidden('approve');
    }

    private function pending(): RechargeRequest
    {
        return RechargeRequest::create([
            'user_id' => $this->user->id, 'amount' => 450000,
            'deposit_method' => 'manual', 'status' => 'pending',
        ]);
    }

    private function approved(): RechargeRequest
    {
        return app(RechargeApprovalService::class)->approve(
            $this->pending(), $this->admin, 'Initial approval',
        );
    }

    private function reverse(RechargeRequest $request): RechargeRequest
    {
        return app(RechargeApprovalService::class)->reverseManualApproval(
            $request, $this->admin, '450000', true, 'Duplicate bank receipt', '127.0.0.1',
        );
    }

    private function wallet(): Wallet
    {
        return Wallet::where('user_id', $this->user->id)->where('type', Wallet::TYPE_RECHARGE)->firstOrFail();
    }
}
