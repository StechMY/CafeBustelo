<?php

namespace Tests\Feature;

use App\Filament\Resources\RechargeRequests\Pages\ListRechargeRequests;
use App\Filament\Resources\RechargeRequests\Pages\ViewRechargeRequest;
use App\Models\AdminUser;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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

class ManualRechargeReapprovalTest extends TestCase
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


    private function reapprove(RechargeRequest $request): RechargeRequest
    {
        return app(RechargeApprovalService::class)->reapproveManual($request, $this->admin, '450000', true, 'Payment verified', '127.0.0.1');
    }

    public function test_rejected_deposit_is_credited_once_and_keeps_previous_review(): void
    {
        $request = app(RechargeApprovalService::class)->reject($this->pending(), $this->admin, 'Receipt unclear');
        $this->reapprove($request);
        $this->assertTrue($request->fresh()->isApproved());
        $this->assertSame('450000.00', $this->wallet()->balance);
        $this->assertSame('450000.00', $this->user->fresh()->accumulated_recharge);
        $this->assertSame('Receipt unclear', WalletTransaction::sole()->meta['previous_admin_remark']);
        $this->assertSame($this->admin->id, $request->fresh()->reviewed_by);
        try { $this->reapprove($request); $this->fail('Repeated approval must fail'); }
        catch (RuntimeException $e) { $this->assertSame(__('recharge.reapproval_not_rejected'), $e->getMessage()); }
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame('450000.00', $this->wallet()->balance);
    }

    public function test_fully_reversed_deposit_can_be_reapproved(): void
    {
        $request = $this->approved();
        $this->reverse($request);
        $this->reapprove($request);
        $this->assertDatabaseCount('wallet_transactions', 3);
        $this->assertSame('450000.00', $this->wallet()->balance);
        $this->assertSame('450000.00', $this->user->fresh()->accumulated_recharge);
        $this->assertTrue($request->fresh()->isApproved());
    }

    #[DataProvider('invalidCases')]
    public function test_invalid_requests_never_credit(string $case): void
    {
        $request = $this->pending();
        $request->update(['status' => 'rejected']);
        if ($case === 'pending') $request->update(['status'=>'pending']);
        if ($case === 'gateway') $request->update(['deposit_method'=>'gateway','gateway_provider'=>'ydpay']);
        if ($case === 'disguised_gateway') $request->update(['gateway_payment_uid'=>'provider-id']);
        if ($case === 'already_credited') {
            $request->update(['status'=>'pending']);
            app(RechargeApprovalService::class)->approve($request, $this->admin);
            $request->update(['status'=>'rejected']);
        }
        if ($case === 'permission') $this->admin->revokePermissionTo('Audit:Recharge');
        $before = [$request->fresh()->status, WalletTransaction::count(), $this->user->fresh()->accumulated_recharge];
        try {
            app(RechargeApprovalService::class)->reapproveManual($request, $this->admin,
                $case === 'amount' ? '1' : '450000', $case !== 'confirmation', $case === 'reason' ? ' ' : 'Verified');
            $this->fail('Invalid approval must fail');
        } catch (RuntimeException|AuthorizationException|ValidationException $e) {
            $this->assertSame($before, [$request->fresh()->status, WalletTransaction::count(), $this->user->fresh()->accumulated_recharge]);
        }
    }

    public static function invalidCases(): array
    {
        return array_map(fn($case)=>[$case], ['pending','gateway','disguised_gateway','already_credited','permission','amount','confirmation','reason']);
    }

    public function test_credit_failure_rolls_back_rejected_status_and_review(): void
    {
        $request = $this->pending();
        $request->update(['status'=>'rejected','admin_remark'=>'Original rejection']);
        $this->mock(WalletService::class)->shouldReceive('credit')->once()->andThrow(new RuntimeException('Credit failure'));
        try { $this->reapprove($request); $this->fail('Expected credit failure'); }
        catch (RuntimeException $e) { $this->assertSame('Credit failure', $e->getMessage()); }
        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('Original rejection', $request->fresh()->admin_remark);
        $this->assertSame('0.00', $this->user->fresh()->accumulated_recharge);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_list_and_detail_actions_credit_and_disappear_after_approval(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $request=$this->pending(); $request->update(['status'=>'rejected']);
        Livewire::test(ListRechargeRequests::class)
            ->callAction(TestAction::make('reapprove')->table($request), data: [
                'confirmed_amount'=>'450000','confirmed'=>true,'reason'=>'Verified payment',
            ])->assertHasNoActionErrors()
            ->assertActionHidden(TestAction::make('reapprove')->table($request));
        $other=$this->pending(); $other->update(['status'=>'rejected']);
        Livewire::test(ViewRechargeRequest::class, ['record'=>$other->id])
            ->callAction('reapprove', data: ['confirmed_amount'=>'450000','confirmed'=>true,'reason'=>'Verified payment'])
            ->assertHasNoActionErrors()->assertActionHidden('reapprove');
        $this->assertTrue($request->fresh()->isApproved());
        $this->assertTrue($other->fresh()->isApproved());
        $this->assertSame('900000.00', $this->wallet()->balance);
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
