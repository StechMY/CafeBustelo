<?php

namespace Tests\Feature;

use App\Exceptions\Qc168OrderRejectedException;
use App\Exceptions\Qc168UncertainSubmissionException;
use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use App\Filament\Resources\WithdrawRequests\Pages\ViewWithdrawRequest;
use App\Filament\Resources\WithdrawRequests\Tables\WithdrawRequestsTable;
use App\Filament\Resources\WithdrawRequests\WithdrawRequestResource;
use App\Models\AdminUser;
use App\Models\PaymentGatewayEvent;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Services\PaymentGateway\PaymentGatewayEventRecorder;
use App\Services\PaymentGateway\Qc168BalanceService;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use App\Support\Api\ApiFormatter;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class Qc168WithdrawalPayoutTest extends TestCase
{
    private ?string $inquiryStatus = null;

    protected function setUp(): void
    {
        $testDatabase = getenv('QC168_TEST_DB_DATABASE');

        if ($testDatabase) {
            putenv('DB_CONNECTION=mysql');
            putenv("DB_DATABASE={$testDatabase}");
            $_ENV['DB_CONNECTION'] = 'mysql';
            $_ENV['DB_DATABASE'] = $testDatabase;
            $_SERVER['DB_CONNECTION'] = 'mysql';
            $_SERVER['DB_DATABASE'] = $testDatabase;
        } else {
            if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                $this->markTestSkipped('SQLite PDO driver is unavailable.');
            }

            putenv('DB_CONNECTION=sqlite');
            putenv('DB_DATABASE=:memory:');
            $_ENV['DB_CONNECTION'] = 'sqlite';
            $_ENV['DB_DATABASE'] = ':memory:';
            $_SERVER['DB_CONNECTION'] = 'sqlite';
            $_SERVER['DB_DATABASE'] = ':memory:';
        }

        parent::setUp();

        config([
            'database.default' => $testDatabase ? 'mysql' : 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.mysql.database' => $testDatabase ?: config('database.connections.mysql.database'),
            'services.qc168.enabled' => true,
            'services.qc168.base_url' => 'https://gateway.example',
            'services.qc168.oc_key' => 'test-key',
            'services.qc168.secret' => 'test-secret',
            'services.qc168.merchant_uid' => 'merchant-123',
            'services.qc168.currency' => 'vnd',
            'services.qc168.country' => 'vn',
            'services.qc168.withdraw_payment' => 'vn02',
        ]);

        $this->artisan('migrate:fresh')->run();
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, true);

        VipLevel::query()->create([
            'level' => 1,
            'name' => 'VIP 1',
            'required_accumulated_recharge' => 0,
            'required_active_team_size' => 0,
            'min_withdraw_amount' => 1,
            'withdraw_fee_percent' => 2,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_manual_approval_finalizes_the_existing_reserve_without_gateway_or_second_ledger_entry(): void
    {
        $withdrawal = $this->pendingWithdrawal('manual');
        $admin = $this->approvalAdmin('manual-admin', ['ApproveManual:Withdrawal']);
        Http::fake();

        app(WithdrawService::class)->approveManually($withdrawal, $admin, 'Paid outside QC168');

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::STATUS_APPROVED, $withdrawal->status);
        $this->assertSame(WithdrawRequest::APPROVAL_MODE_MANUAL, $withdrawal->approval_mode);
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->payout_status);
        $this->assertNull($withdrawal->gateway_provider);
        $this->assertNull($withdrawal->gateway_release_at);
        $this->assertNull($withdrawal->gateway_completed_at);
        $this->assertWallet('manual', '0.00', '0.00');
        $this->assertDatabaseCount('withdrawal_payout_attempts', 0);
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'event_type' => 'withdrawal.approved_manual',
            'actor_admin_user_id' => $admin->id,
            'provider' => 'internal',
        ]);

        try {
            $withdrawal->forceFill([
                'approval_mode' => WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED,
            ])->save();
            $this->fail('The snapshotted approval mode must be immutable.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }

        Http::assertNothingSent();
    }

    public function test_revert_manual_approval_restores_full_reserve_and_allows_gateway_reapproval(): void
    {
        $withdrawal = $this->pendingWithdrawal('undo_manual');
        $admin = $this->approvalAdmin('undo-admin', ['ApproveManual:Withdrawal', 'ApproveScheduledGateway:Withdrawal']);
        $service = app(WithdrawService::class);
        $paid = $service->approveManually($withdrawal, $admin, 'Clicked by mistake.');
        $approval = $paid->manualApprovalEvents()->sole();
        // Another withdrawal's reserve must not be overwritten by the restoration.
        Wallet::where('user_id', $paid->user_id)->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->update(['locked_balance' => '500.00']);
        Http::fake();

        $pending = $service->revertManualApproval($paid, $admin, $approval->id, 'No transfer was made.', true, '127.0.0.1');

        $this->assertTrue($pending->isPending());
        $this->assertNull($pending->approval_mode);
        $this->assertNull($pending->payout_status);
        $this->assertNull($pending->reviewed_at);
        $this->assertNull($pending->reviewed_by);
        $this->assertNull($pending->admin_remark);
        $this->assertWallet('undo_manual', '0.00', '1500.00');
        $this->assertDatabaseCount('wallet_transactions', 1);
        $event = PaymentGatewayEvent::where('event_type', 'withdrawal.manual_approval_reverted')->sole();
        $this->assertSame($approval->id, $event->payload['approval_event_id']);
        $this->assertSame('500.00', $event->payload['locked_balance_before']);
        $this->assertSame('1500.00', $event->payload['locked_balance_after']);
        $this->assertSame('Clicked by mistake.', $event->payload['before']['admin_remark']);
        $this->assertSame($admin->id, $event->actor_admin_user_id);
        $this->assertSame('127.0.0.1', $event->payload['ip']);
        $this->assertDatabaseHas('payment_gateway_events', ['id' => $approval->id, 'event_type' => 'withdrawal.approved_manual']);

        $service->approveWithScheduledGateway($pending, $admin);
        $this->assertSame(WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED, $pending->fresh()->approval_mode);
        $this->assertWallet('undo_manual', '0.00', '1500.00');
        Http::assertNothingSent();
    }

    public function test_revert_manual_approval_cannot_restore_twice_or_replay_an_older_approval(): void
    {
        $withdrawal = $this->pendingWithdrawal('undo_replay');
        $admin = $this->approvalAdmin('undo-admin', ['ApproveManual:Withdrawal']);
        $service = app(WithdrawService::class);
        $paid = $service->approveManually($withdrawal, $admin);
        $oldEventId = $paid->manualApprovalEvents()->sole()->id;
        $pending = $service->revertManualApproval($paid, $admin, $oldEventId, 'Mistake.', true);
        try {
            $service->revertManualApproval($paid, $admin, $oldEventId, 'Duplicate click.', true);
            $this->fail('Duplicate restoration must be blocked.');
        } catch (RuntimeException) {
            $this->assertWallet('undo_replay', '0.00', '1000.00');
        }
        $paidAgain = $service->approveManually($pending, $admin);
        try {
            $service->revertManualApproval($paidAgain, $admin, $oldEventId, 'Stale browser tab.', true);
            $this->fail('The previous approval must not authorize undoing a new approval.');
        } catch (RuntimeException) {
            $this->assertWallet('undo_replay', '0.00', '0.00');
        }
        $pendingAgain = $service->revertManualApproval(
            $paidAgain, $admin, $paidAgain->manualApprovalEvents()->latest('id')->firstOrFail()->id, 'New mistake.', true,
        );
        $service->reject($pendingAgain, $admin, 'Return funds instead.');
        $this->assertWallet('undo_replay', '1000.00', '0.00');
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_revert_manual_approval_requires_permission_reason_and_unpaid_confirmation(): void
    {
        $withdrawal = $this->pendingWithdrawal('undo_permission');
        $admin = $this->approvalAdmin('undo-admin', ['ApproveManual:Withdrawal']);
        $service = app(WithdrawService::class);
        $paid = $service->approveManually($withdrawal, $admin);
        $eventId = $paid->manualApprovalEvents()->sole()->id;
        try {
            $service->revertManualApproval($paid, $this->admin(), $eventId, 'Mistake.', true);
            $this->fail('Manual approval permission is required.');
        } catch (AuthorizationException) {
            $this->assertWallet('undo_permission', '0.00', '0.00');
        }
        foreach ([[' ', true], ['Mistake.', false]] as [$reason, $unpaid]) {
            try {
                $service->revertManualApproval($paid, $admin, $eventId, $reason, $unpaid);
                $this->fail('Reason and unpaid confirmation are required.');
            } catch (ValidationException) {
                $this->assertTrue($paid->fresh()->isPayoutCompleted());
            }
        }
        $this->assertSame(0, PaymentGatewayEvent::where('event_type', 'withdrawal.manual_approval_reverted')->count());
    }

    public function test_revert_manual_approval_blocks_gateway_activity_and_missing_accounting_evidence(): void
    {
        $admin = $this->approvalAdmin('undo-admin', ['ApproveManual:Withdrawal']);
        $service = app(WithdrawService::class);
        foreach (['gateway_field', 'attempt', 'missing_event', 'refunded'] as $scenario) {
            $paid = $service->approveManually($this->pendingWithdrawal('undo_'.$scenario), $admin);
            $eventId = $paid->manualApprovalEvents()->sole()->id;
            if ($scenario === 'gateway_field') {
                $paid->forceFill(['gateway_submitted_at' => now()])->save();
            } elseif ($scenario === 'attempt') {
                $paid->payoutAttempts()->create([
                    'attempt_no' => 1, 'provider' => 'qc168', 'merchant_order_id' => 'unexpected-'.$paid->id,
                    'amount' => $paid->net_amount, 'currency' => 'vnd', 'payment_channel' => 'vn02', 'gateway_status' => 'completed',
                    'bank_name_snapshot' => $paid->bank_name, 'bank_code_snapshot' => $paid->bank_code,
                    'bank_account_name_snapshot' => $paid->bank_account_name, 'bank_account_no_snapshot' => $paid->bank_account_no,
                ]);
            } elseif ($scenario === 'missing_event') {
                DB::table('payment_gateway_events')->where('id', $eventId)->delete();
            } else {
                $paid->gatewayCancellationTransactions()->create([
                    'user_id' => $paid->user_id, 'wallet_id' => Wallet::where('user_id', $paid->user_id)->where('type', Wallet::TYPE_WITHDRAWABLE)->sole()->id,
                    'wallet_type' => Wallet::TYPE_WITHDRAWABLE, 'type' => 'withdraw_gateway_cancelled', 'direction' => 'credit',
                    'amount' => '1000.00', 'balance_before' => '0.00', 'balance_after' => '1000.00',
                ]);
            }
            try {
                $service->revertManualApproval($paid, $admin, $eventId, 'Mistake.', true);
                $this->fail('An unsafe restoration must be blocked.');
            } catch (RuntimeException) {
                $this->assertWallet('undo_'.$scenario, '0.00', '0.00');
                $this->assertTrue($paid->fresh()->isPayoutCompleted());
            }
        }
    }

    public function test_revert_manual_approval_rolls_back_wallet_and_status_when_audit_record_fails(): void
    {
        $admin = $this->approvalAdmin('undo-admin', ['ApproveManual:Withdrawal']);
        $paid = app(WithdrawService::class)->approveManually($this->pendingWithdrawal('undo_atomic'), $admin);
        $eventId = $paid->manualApprovalEvents()->sole()->id;
        $this->mock(PaymentGatewayEventRecorder::class)
            ->shouldReceive('recordWithdrawal')->once()->andThrow(new RuntimeException('Audit unavailable.'));
        try {
            app(WithdrawService::class)->revertManualApproval($paid, $admin, $eventId, 'Mistake.', true);
            $this->fail('The failed audit must roll back the restoration.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
            $this->assertWallet('undo_atomic', '0.00', '0.00');
            $this->assertTrue($paid->fresh()->isPayoutCompleted());
            $this->assertSame(WithdrawRequest::APPROVAL_MODE_MANUAL, $paid->fresh()->approval_mode);
        }
    }

    public function test_revert_manual_approval_action_returns_detail_page_to_pending_actions(): void
    {
        $admin = $this->approvalAdmin('undo-admin', ['ApproveManual:Withdrawal', 'ViewAny:WithdrawRequest', 'View:WithdrawRequest']);
        $paid = app(WithdrawService::class)->approveManually($this->pendingWithdrawal('undo_ui'), $admin);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListWithdrawRequests::class)
            ->assertActionVisible(TestAction::make('revert_manual_approval')->table($paid));
        $page = Livewire::test(ViewWithdrawRequest::class, ['record' => $paid->getRouteKey()])
            ->mountAction('revert_manual_approval')
            ->assertActionDataSet(['approval_event_id' => $paid->manualApprovalEvents()->sole()->id]);
        $page->setActionData(['reason' => 'No transfer made.', 'confirmed_unpaid' => false])
            ->callMountedAction()->assertHasActionErrors(['confirmed_unpaid']);
        $page->setActionData(['reason' => 'No transfer made.', 'confirmed_unpaid' => true])
            ->callMountedAction()->assertHasNoActionErrors()
            ->assertActionHidden('revert_manual_approval')->assertActionVisible('approve_manual');
        $this->assertTrue($paid->fresh()->isPending());
        $this->assertWallet('undo_ui', '0.00', '1000.00');
    }

    public function test_instant_gateway_approval_commits_then_submits_using_the_shared_attempt_path(): void
    {
        Carbon::setTestNow('2026-08-26 15:00:00');
        $withdrawal = $this->pendingWithdrawal('instant');
        $admin = $this->approvalAdmin('instant-admin', ['ApproveInstantGateway:Withdrawal']);
        Http::fake(fn () => $this->createOrderResponse());

        app(WithdrawService::class)->approveWithInstantGateway($withdrawal, $admin);

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::STATUS_APPROVED, $withdrawal->status);
        $this->assertSame(WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT, $withdrawal->approval_mode);
        $this->assertSame(0, $withdrawal->gateway_release_delay_hours_snapshot);
        $this->assertTrue($withdrawal->gateway_release_at->equalTo($withdrawal->reviewed_at));
        $this->assertSame(WithdrawRequest::PAYOUT_PENDING, $withdrawal->payout_status);
        $this->assertWallet('instant', '0.00', '1000.00');
        $this->assertDatabaseCount('withdrawal_payout_attempts', 1);
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'event_type' => 'withdrawal.gateway_instant_attempt_created',
            'actor_admin_user_id' => $admin->id,
        ]);
        Http::assertSentCount(1);
    }

    public function test_instant_gateway_completed_response_consumes_locked_reserve_once(): void
    {
        $withdrawal = $this->pendingWithdrawal('instant_complete');
        $admin = $this->approvalAdmin('instant-complete-admin', ['ApproveInstantGateway:Withdrawal']);
        Http::fake(function (ClientRequest $request) {
            return Http::response([
                'result' => 'success',
                'data' => [
                    'payment' => [
                        'transaction_type' => 'expense',
                        'accounting_type' => 'pay',
                        'merchant_uid' => 'merchant-123',
                        'merchant_order_id' => $request['merchant_order_id'],
                        'amount' => $request['amount'],
                        'final_amount' => $request['amount'],
                        'currency' => 'vnd',
                        'payment' => 'vn02',
                        'payment_status' => 'completed',
                        'payment_uid' => 'PAY-INSTANT-COMPLETE',
                    ],
                ],
            ]);
        });

        app(WithdrawService::class)->approveWithInstantGateway($withdrawal, $admin);

        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->fresh()->payout_status);
        $this->assertWallet('instant_complete', '0.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertSame(1, PaymentGatewayEvent::query()
            ->where('withdraw_request_id', $withdrawal->id)
            ->where('event_type', 'withdrawal.gateway_completed')
            ->count());
    }

    public function test_instant_submission_failure_keeps_the_committed_approval_and_reserve_locked(): void
    {
        $withdrawal = $this->pendingWithdrawal('instant_failed');
        $admin = $this->approvalAdmin('instant-failed-admin', ['ApproveInstantGateway:Withdrawal']);
        Http::fake(fn () => Http::response([
            'result' => 'error',
            'data' => 'No bank cards available',
        ], 422));

        try {
            app(WithdrawService::class)->approveWithInstantGateway($withdrawal, $admin);
            $this->fail('The provider submission should have failed.');
        } catch (RuntimeException) {
            // The approval transaction must remain committed after this external failure.
        }

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::STATUS_APPROVED, $withdrawal->status);
        $this->assertSame(WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT, $withdrawal->approval_mode);
        $this->assertSame(WithdrawRequest::PAYOUT_CREATE_FAILED, $withdrawal->payout_status);
        $this->assertWallet('instant_failed', '0.00', '1000.00');
        $this->assertDatabaseCount('withdrawal_payout_attempts', 1);
        $this->assertDatabaseHas('withdrawal_payout_attempts', [
            'withdraw_request_id' => $withdrawal->id,
            'gateway_status' => WithdrawalPayoutAttempt::STATUS_CREATE_FAILED,
        ]);
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->assertFalse(data_get($attempt->provider_meta, 'order_created'));
        $this->assertTrue(data_get($attempt->provider_meta, 'provider_result_error'));
        $this->assertSame(
            'Payout Submission Failed',
            $this->withdrawTableValue('statusLabel', $withdrawal)
        );
        $this->assertStringContainsString(
            'did not create',
            $this->withdrawTableValue('gatewayFriendlyAlert', $withdrawal)
        );
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'event_type' => 'withdrawal.gateway_create_failed',
            'processing_result' => 'create_failed',
        ]);

        $requestCount = Http::recorded()->count();
        $this->artisan('qc168:sync-pending-withdrawals')->assertSuccessful();
        $this->assertCount($requestCount, Http::recorded());
    }

    public function test_explicit_create_failure_can_retry_with_a_new_immutable_attempt(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('create_failed_retry', retrySucceeds: true);
        $firstAttempt = $withdrawal->payoutAttempts()->firstOrFail();

        $secondAttempt = app(Qc168WithdrawalPayoutService::class)->retry(
            $withdrawal,
            $this->admin(),
            'Retry confirmed non-created QC168 order.',
        );

        $this->assertNotNull($secondAttempt);
        $this->assertSame(2, $withdrawal->payoutAttempts()->count());
        $this->assertNotSame($firstAttempt->merchant_order_id, $secondAttempt->merchant_order_id);
        $this->assertSame(
            WithdrawalPayoutAttempt::STATUS_CREATE_FAILED,
            $firstAttempt->fresh()->gateway_status,
        );
        $this->assertWallet('create_failed_retry', '0.00', '1000.00');
    }

    public function test_explicit_create_failure_can_cancel_and_release_reserve_once(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('create_failed_cancel');
        $requestCount = Http::recorded()->count();

        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal,
            $this->admin(),
            'Cancel confirmed non-created QC168 order.',
        );

        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->fresh()->payout_status);
        $this->assertWallet('create_failed_cancel', '1000.00', '0.00');
        $this->assertCount($requestCount, Http::recorded());
        $this->assertSame(2, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
    }

    public function test_signed_callback_after_explicit_payout_create_rejection_is_audited_and_finalized_once(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('payout_rejection_contradiction');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $payload = $this->providerPayload($attempt, 'completed');

        $this->sendCallback($payload)->assertOk();
        $this->sendCallback($payload)->assertOk();

        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->fresh()->payout_status);
        $this->assertWallet('payout_rejection_contradiction', '0.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $event = PaymentGatewayEvent::query()
            ->where('withdraw_request_id', $withdrawal->id)
            ->where('event_type', 'gateway.withdrawal.callback.create_rejected_contradiction')
            ->firstOrFail();
        $this->assertSame('high', $event->payload['audit_priority']);
    }

    public function test_completed_payout_invalidates_balance_after_commit_but_duplicate_does_not(): void
    {
        $withdrawal = $this->approvedAndDispatched('balance_invalidation');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $balanceService = \Mockery::mock(Qc168BalanceService::class);
        $balanceService->shouldReceive('markStale')
            ->once()
            ->andReturnUsing(function () use ($withdrawal): void {
                $this->assertSame(0, DB::transactionLevel());
                $this->assertSame(
                    WithdrawRequest::PAYOUT_COMPLETED,
                    $withdrawal->fresh()->payout_status,
                );
            });
        $this->app->instance(Qc168BalanceService::class, $balanceService);
        $payoutService = app(Qc168WithdrawalPayoutService::class);
        $payload = $this->providerPayload($attempt, 'completed');

        $completed = $payoutService->processProviderState($attempt, $payload, 'test');
        $duplicate = $payoutService->processProviderState($attempt->fresh(), $payload, 'test');

        $this->assertSame('payout_completed', $completed['result']);
        $this->assertSame('duplicate_completed', $duplicate['result']);
    }

    public function test_withdrawal_resource_preloads_row_dependencies_and_cancellation_state(): void
    {
        $withdrawal = $this->pendingWithdrawal('list-performance');
        $withdrawal->forceFill([
            'status' => WithdrawRequest::STATUS_APPROVED,
            'approval_mode' => WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT,
            'gateway_provider' => WithdrawRequest::GATEWAY_QC168,
            'payout_status' => WithdrawRequest::PAYOUT_FAILED,
        ])->save();

        $record = WithdrawRequestResource::getEloquentQuery()
            ->findOrFail($withdrawal->getKey());

        $this->assertTrue($record->relationLoaded('user'));
        $this->assertTrue($record->relationLoaded('reviewer'));
        $this->assertArrayHasKey(
            'gateway_cancellation_transactions_exists',
            $record->getAttributes(),
        );

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertTrue($record->canRetryPayout(usePreloadedCancellationState: true));
        $this->assertTrue($record->canCancelPayout(usePreloadedCancellationState: true));
        $this->assertSame([], DB::getQueryLog());

        $wallet = $withdrawal->user->wallets()
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->firstOrFail();
        WalletTransaction::query()->create([
            'user_id' => $withdrawal->user_id,
            'wallet_id' => $wallet->getKey(),
            'wallet_type' => Wallet::TYPE_WITHDRAWABLE,
            'type' => 'withdraw_gateway_cancelled',
            'direction' => WalletTransaction::DIRECTION_CREDIT,
            'amount' => '980.00',
            'balance_before' => '0.00',
            'balance_after' => '980.00',
            'reference_type' => WithdrawRequest::class,
            'reference_id' => $withdrawal->getKey(),
        ]);

        $this->assertTrue($record->hasGatewayCancellationLedger());
        $this->assertFalse($record->canRetryPayout());
    }

    public function test_restricted_support_reuses_downline_ids_within_the_request(): void
    {
        $managedUser = $this->user('managed-user');
        $child = $this->user('managed-child');
        $grandchild = $this->user('managed-grandchild');
        $child->forceFill(['referrer_id' => $managedUser->getKey()])->save();
        $grandchild->forceFill(['referrer_id' => $child->getKey()])->save();

        $admin = $this->approvalAdmin('managed-support', []);
        $admin->forceFill(['managed_user_id' => $managedUser->getKey()])->save();
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));

        $expected = [$child->getKey(), $grandchild->getKey()];
        $this->assertSame($expected, $admin->managedDownlineUserIds());

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->assertSame($expected, $admin->managedDownlineUserIds());
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_withdrawal_list_avoids_per_record_scope_and_cancellation_queries(): void
    {
        config([
            'services.qc168.enabled' => false,
            'sms.enabled' => false,
        ]);

        $managedUser = $this->user('list-managed-user');

        foreach (range(1, 17) as $index) {
            $withdrawal = $this->pendingWithdrawal('list-row-'.$index);
            $withdrawal->user->forceFill([
                'referrer_id' => $managedUser->getKey(),
            ])->save();

            if ($index > 8) {
                $withdrawal->forceFill([
                    'status' => WithdrawRequest::STATUS_APPROVED,
                    'approval_mode' => WithdrawRequest::APPROVAL_MODE_GATEWAY_INSTANT,
                    'gateway_provider' => WithdrawRequest::GATEWAY_QC168,
                    'payout_status' => WithdrawRequest::PAYOUT_FAILED,
                ])->save();
            }
        }

        $admin = $this->approvalAdmin('list-support', [
            'ViewAny:WithdrawRequest',
            'ApproveManual:Withdrawal',
            'ApproveScheduledGateway:Withdrawal',
            'ApproveInstantGateway:Withdrawal',
            'RetryGateway:Withdrawal',
            'CancelGateway:Withdrawal',
        ]);
        $admin->forceFill(['managed_user_id' => $managedUser->getKey()])->save();
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->actingAs($admin, 'admin')
            ->get('/admin/withdraw-requests')
            ->assertOk();

        $queries = collect(DB::getQueryLog());
        $directCancellationQueries = $queries->filter(
            fn (array $query): bool => str_contains($query['query'], 'select exists')
                && str_contains($query['query'], 'wallet_transactions'),
        );
        $downlineQueries = $queries->filter(
            fn (array $query): bool => str_contains($query['query'], 'from `users`')
                && str_contains($query['query'], '`referrer_id` in'),
        );
        $gatewaySettingQueries = $queries->filter(
            fn (array $query): bool => str_contains($query['query'], 'from `settings`')
                && in_array($query['bindings'][0] ?? null, [
                    Qc168Settings::KEY_WITHDRAW_ENABLED,
                    Qc168Settings::KEY_WITHDRAW_RELEASE_HOURS,
                ], true),
        );

        $this->assertCount(0, $directCancellationQueries);
        $this->assertLessThanOrEqual(2, $downlineQueries->count());
        $this->assertLessThanOrEqual(2, $gatewaySettingQueries->count());
    }

    public function test_gateway_switches_block_gateway_modes_but_never_manual_approval(): void
    {
        $admin = $this->approvalAdmin('all-modes-admin', [
            'ApproveManual:Withdrawal',
            'ApproveScheduledGateway:Withdrawal',
            'ApproveInstantGateway:Withdrawal',
        ]);
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, false);
        Http::fake();

        $manual = $this->pendingWithdrawal('disabled_manual');
        app(WithdrawService::class)->approveManually($manual, $admin);
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $manual->fresh()->payout_status);

        $scheduled = $this->pendingWithdrawal('disabled_scheduled');
        try {
            app(WithdrawService::class)->approveWithScheduledGateway($scheduled, $admin);
            $this->fail('Scheduled approval should be blocked by the admin gateway switch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdrawal', $exception->errors());
        }

        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, true);
        config(['services.qc168.enabled' => false]);
        $instant = $this->pendingWithdrawal('disabled_instant');
        try {
            app(WithdrawService::class)->approveWithInstantGateway($instant, $admin);
            $this->fail('Instant approval should be blocked by the master gateway switch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdrawal', $exception->errors());
        }

        $this->assertTrue($scheduled->fresh()->isPending());
        $this->assertTrue($instant->fresh()->isPending());
        Http::assertNothingSent();
    }

    public function test_each_approval_mode_requires_its_own_permission(): void
    {
        $manualOnly = $this->approvalAdmin('manual-only-admin', ['ApproveManual:Withdrawal']);
        $scheduledOnly = $this->approvalAdmin('scheduled-only-admin', ['ApproveScheduledGateway:Withdrawal']);
        $instantOnly = $this->approvalAdmin('instant-only-admin', ['ApproveInstantGateway:Withdrawal']);

        foreach ([
            fn () => app(WithdrawService::class)->approveWithInstantGateway($this->pendingWithdrawal('permission_instant'), $manualOnly),
            fn () => app(WithdrawService::class)->approveManually($this->pendingWithdrawal('permission_manual'), $scheduledOnly),
            fn () => app(WithdrawService::class)->approveWithScheduledGateway($this->pendingWithdrawal('permission_scheduled'), $instantOnly),
        ] as $unauthorizedAction) {
            try {
                $unauthorizedAction();
                $this->fail('A different approval permission must not authorize this mode.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_duplicate_approval_actions_do_not_consume_or_submit_twice(): void
    {
        $manualAdmin = $this->approvalAdmin('duplicate-manual-admin', ['ApproveManual:Withdrawal']);
        $manual = $this->pendingWithdrawal('duplicate_manual');
        app(WithdrawService::class)->approveManually($manual, $manualAdmin);
        try {
            app(WithdrawService::class)->approveManually($manual, $manualAdmin);
            $this->fail('A duplicate manual approval should fail.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }
        $this->assertWallet('duplicate_manual', '0.00', '0.00');
        $this->assertSame(1, PaymentGatewayEvent::query()
            ->where('withdraw_request_id', $manual->id)
            ->where('event_type', 'withdrawal.approved_manual')
            ->count());

        $scheduledAdmin = $this->approvalAdmin('duplicate-scheduled-admin', ['ApproveScheduledGateway:Withdrawal']);
        $scheduled = $this->pendingWithdrawal('duplicate_scheduled');
        app(WithdrawService::class)->approveWithScheduledGateway($scheduled, $scheduledAdmin);
        try {
            app(WithdrawService::class)->approveWithScheduledGateway($scheduled, $scheduledAdmin);
            $this->fail('A duplicate scheduled approval should fail.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $instantAdmin = $this->approvalAdmin('duplicate-instant-admin', ['ApproveInstantGateway:Withdrawal']);
        $instant = $this->pendingWithdrawal('duplicate_instant');
        Http::fake(fn () => $this->createOrderResponse());
        app(WithdrawService::class)->approveWithInstantGateway($instant, $instantAdmin);
        try {
            app(WithdrawService::class)->approveWithInstantGateway($instant, $instantAdmin);
            $this->fail('A duplicate instant approval should fail.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        Http::assertSentCount(1);
        $this->assertSame(1, $instant->payoutAttempts()->count());
    }

    public function test_legacy_approved_withdrawals_backfill_as_manual_and_are_never_dispatched(): void
    {
        $approvalModesMigration = require database_path('migrations/2026_08_28_000001_add_withdrawal_approval_modes.php');
        $approvalModesMigration->down();

        $withdrawal = $this->pendingWithdrawal('legacy_approved');
        $withdrawal->forceFill([
            'status' => WithdrawRequest::STATUS_APPROVED,
            'payout_status' => WithdrawRequest::PAYOUT_COMPLETED,
            'reviewed_at' => now(),
            'gateway_completed_at' => now(),
        ])->save();

        $approvalModesMigration->up();
        $withdrawal->refresh();

        $this->assertSame(WithdrawRequest::APPROVAL_MODE_MANUAL, $withdrawal->approval_mode);
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->payout_status);

        Http::fake();
        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertDatabaseCount('withdrawal_payout_attempts', 0);
    }

    public function test_disabled_gateway_blocks_approval_but_rejection_still_refunds(): void
    {
        $withdrawal = $this->pendingWithdrawal('disabled');
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, false);

        try {
            app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());
            $this->fail('Approval should have been blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('withdrawal', $exception->errors());
        }

        $this->assertSame(WithdrawRequest::STATUS_PENDING, $withdrawal->fresh()->status);
        $this->assertWallet('disabled', '0.00', '1000.00');

        app(WithdrawService::class)->reject($withdrawal, $this->admin(), 'Rejected by admin');

        $this->assertSame(WithdrawRequest::STATUS_REJECTED, $withdrawal->fresh()->status);
        $this->assertWallet('disabled', '1000.00', '0.00');
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    public function test_approval_starts_exact_24_hour_hold_without_consuming_reserve(): void
    {
        Carbon::setTestNow('2026-08-26 14:32:00');
        $withdrawal = $this->pendingWithdrawal('approval');

        Http::fake();
        app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin(), 'Approved');

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::STATUS_APPROVED, $withdrawal->status);
        $this->assertSame(WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED, $withdrawal->approval_mode);
        $this->assertSame(WithdrawRequest::PAYOUT_WAITING_RELEASE, $withdrawal->payout_status);
        $this->assertSame('2026-08-27 14:32:00', $withdrawal->gateway_release_at->format('Y-m-d H:i:s'));
        $this->assertSame(24, $withdrawal->gateway_release_delay_hours_snapshot);
        $this->assertWallet('approval', '0.00', '1000.00');
        $this->assertSame(1, WalletTransaction::query()->count());
        Http::assertNothingSent();
    }

    public function test_dispatch_waits_until_exact_release_time_and_uses_snapshot_net_amount(): void
    {
        Carbon::setTestNow('2026-08-26 14:32:00');
        $withdrawal = $this->pendingWithdrawal('timing');
        app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());

        Carbon::setTestNow('2026-08-27 14:31:59');
        Http::fake();
        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertDatabaseCount('withdrawal_payout_attempts', 0);

        Carbon::setTestNow('2026-08-27 14:32:00');
        Http::fake(function (ClientRequest $request) use ($withdrawal) {
            $this->assertStringEndsWith('/octopus/api/order/new', $request->url());
            $this->assertSame('expense', $request['transaction_type']);
            $this->assertSame('pay', $request['accounting_type']);
            $this->assertSame('980.00', $request['amount']);
            $this->assertSame('vnd', $request['currency']);
            $this->assertSame('vn', $request['country']);
            $this->assertSame('vn02', $request['payment']);
            $this->assertSame($withdrawal->bank_account_no, $request['to']);
            $this->assertSame('Nguyen Hoa', $request['to_name']);
            $this->assertSame('970436', $request['to_bank_name']);
            $this->assertDatabaseHas('withdrawal_payout_attempts', [
                'withdraw_request_id' => $withdrawal->id,
                'merchant_order_id' => $request['merchant_order_id'],
                'amount' => '980.00',
            ]);

            return $this->createOrderResponse();
        });

        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('withdrawal_payout_attempts', 1);
        $this->assertWallet('timing', '0.00', '1000.00');
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_withdrawal_scheduler_locks_expire_after_a_crashed_process(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(function ($event): bool {
            return str_contains((string) $event->command, 'qc168:dispatch-approved-withdrawals')
                || str_contains((string) $event->command, 'qc168:sync-pending-withdrawals');
        });
        $this->assertCount(2, $events);

        foreach ($events as $event) {
            Carbon::setTestNow('2026-09-29 14:29:00');
            $originalProcess = (new CacheEventMutex(app('cache')))->useStore('database');
            $this->assertTrue($originalProcess->create($event));

            // Simulate a restart: the original process never releases its database lock.
            $restartedProcess = (new CacheEventMutex(app('cache')))->useStore('database');
            $this->assertFalse($restartedProcess->create($event));
            Carbon::setTestNow('2026-09-29 14:33:59');
            $this->assertFalse($restartedProcess->create($event));
            Carbon::setTestNow('2026-09-29 14:34:00');
            $this->assertTrue($restartedProcess->create($event));
            $restartedProcess->forget($event);
        }
    }

    public function test_dispatch_yields_between_orders_and_resumes_without_duplicate_submissions(): void
    {
        Carbon::setTestNow('2026-09-29 14:29:00');
        $withdrawals = [];
        for ($index = 0; $index < 3; $index++) {
            $withdrawal = $this->pendingWithdrawal('dispatch_budget_'.$index);
            app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());
            $withdrawal->forceFill(['gateway_release_at' => now()->subMinutes(3 - $index)])->save();
            $withdrawals[] = $withdrawal;
        }

        Http::fake(function () {
            Carbon::setTestNow(now()->addSeconds(45));

            return $this->createOrderResponse();
        });

        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame(WithdrawRequest::PAYOUT_PENDING, $withdrawals[0]->fresh()->payout_status);
        $this->assertSame(WithdrawRequest::PAYOUT_WAITING_RELEASE, $withdrawals[1]->fresh()->payout_status);
        $this->assertSame(WithdrawRequest::PAYOUT_WAITING_RELEASE, $withdrawals[2]->fresh()->payout_status);

        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        $this->artisan('qc168:dispatch-approved-withdrawals')->assertSuccessful();
        Http::assertSentCount(3);
        foreach ($withdrawals as $withdrawal) {
            $this->assertSame(1, $withdrawal->payoutAttempts()->count());
        }
        $this->assertWallet('dispatch_budget_0', '0.00', '1000.00');
        $this->assertSame(3, WalletTransaction::query()->count());
    }

    public function test_sync_yields_between_orders_and_resumes_remaining_attempts(): void
    {
        Carbon::setTestNow('2026-09-29 14:29:00');
        $first = $this->approvedAndDispatched('sync_budget_first');
        $second = $this->approvedAndDispatched('sync_budget_second');
        Carbon::setTestNow(now()->addMinutes(3));
        Http::swap(new Factory);
        Http::fake(function (ClientRequest $request) {
            $this->assertStringEndsWith('/octopus/api/order/get', $request->url());
            Carbon::setTestNow(now()->addSeconds(45));
            $attempt = WithdrawalPayoutAttempt::query()
                ->where('merchant_order_id', $request['merchant_order_id'])->firstOrFail();

            return Http::response([
                'result' => 'success',
                'data' => $this->providerPayload($attempt, 'pending'),
            ]);
        });

        $this->artisan('qc168:sync-pending-withdrawals')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertNotNull($first->fresh()->gateway_last_synced_at);
        $this->assertNull($second->fresh()->gateway_last_synced_at);

        $this->artisan('qc168:sync-pending-withdrawals')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertNotNull($second->fresh()->gateway_last_synced_at);
        $this->assertDatabaseCount('withdrawal_payout_attempts', 2);
    }

    public function test_completed_callback_consumes_locked_reserve_exactly_once(): void
    {
        $withdrawal = $this->approvedAndDispatched('complete');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $payload = $this->providerPayload($attempt, 'completed');

        for ($callback = 0; $callback < 3; $callback++) {
            $this->sendCallback($payload)->assertOk()->assertSeeText('success');
        }

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->payout_status);
        $this->assertWallet('complete', '0.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertSame(1, PaymentGatewayEvent::query()
            ->where('withdraw_request_id', $withdrawal->id)
            ->where('event_type', 'withdrawal.gateway_completed')
            ->count());
    }

    public function test_withdrawal_table_keeps_handling_in_status_tooltip_and_sorts_by_payout_schedule(): void
    {
        Http::fake();
        $admin = $this->approvalAdmin('handling-list-admin', ['ViewAny:WithdrawRequest', 'View:WithdrawRequest']);
        $later = $this->pendingWithdrawal('scheduled_later');
        $earlier = $this->pendingWithdrawal('scheduled_earlier');
        $later->forceFill([
            'status' => 'approved',
            'gateway_provider' => 'qc168',
            'approval_mode' => 'gateway_scheduled',
            'payout_status' => 'processing',
            'gateway_payment_status' => 'processing',
            'gateway_release_at' => '2026-10-02 10:34:16',
            'gateway_submitted_at' => '2026-10-02 10:35:04',
            'gateway_last_synced_at' => '2026-10-02 12:00:09',
        ])->save();
        $earlier->forceFill([
            'status' => 'approved',
            'gateway_provider' => 'qc168',
            'approval_mode' => 'gateway_scheduled',
            'payout_status' => 'create_failed',
            'gateway_release_at' => '2026-10-02 10:29:22',
            'gateway_fail_reason' => 'QC168 rejected the request: 商户余额不足',
        ])->save();

        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListWithdrawRequests::class)
            ->assertTableColumnDoesNotExist('platform_handling')
            ->assertTableColumnVisible('withdrawal_summary_status')
            ->assertTableColumnVisible('gateway_release_at')
            ->assertTableColumnDoesNotExist('gateway_last_synced_at')
            ->assertTableColumnExists('withdrawal_summary_status', fn ($column): bool =>
                str_contains($column->getTooltip(), 'Submitted · Awaiting gateway'), $later)
            ->assertTableColumnExists('withdrawal_summary_status', fn ($column): bool =>
                str_contains($column->getTooltip(), 'Attempted · Submission failed')
                && str_contains($column->getTooltip(), '商户余额不足'), $earlier)
            ->sortTable('gateway_release_at', 'asc')
            ->assertCanSeeTableRecords([$earlier, $later], inOrder: true)
            ->sortTable('gateway_release_at', 'desc')
            ->assertCanSeeTableRecords([$later, $earlier], inOrder: true)
            ->filterTable('payout_status', 'processing')
            ->assertCanSeeTableRecords([$later])
            ->assertCanNotSeeTableRecords([$earlier])
            ->filterTable('payout_status', 'create_failed')
            ->assertCanSeeTableRecords([$earlier])
            ->assertCanNotSeeTableRecords([$later])
            ->removeTableFilter('payout_status')
            ->assertCanSeeTableRecords([$later, $earlier], inOrder: true);
    }

    public function test_processing_with_diagnostic_reason_stays_processing_and_is_not_retryable_or_cancellable(): void
    {
        $withdrawal = $this->approvedAndDispatched('processing_warning');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $reason = 'CallserverErr: Client.Timeout exceeded while awaiting headers';

        $this->sendCallback(
            $this->providerPayload($attempt, 'processing', $reason)
        )->assertOk();

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_PROCESSING, $withdrawal->payout_status);
        $this->assertSame('processing', $withdrawal->gateway_payment_status);
        $this->assertSame($reason, $withdrawal->gateway_fail_reason);
        $this->assertSame('Payment Processing', $this->withdrawTableValue('statusLabel', $withdrawal));
        $this->assertSame('info', $this->withdrawTableValue('statusColor', $withdrawal));
        $this->assertStringContainsString(
            'timed out',
            strtolower($this->withdrawTableValue('gatewayFriendlyAlert', $withdrawal))
        );
        $this->assertFalse($this->withdrawTableValue('hasGatewayAttention', $withdrawal));
        $this->assertTrue($this->withdrawTableValue('hasGatewayWarning', $withdrawal));
        $this->assertFalse($withdrawal->canRetryPayout());
        $this->assertFalse($withdrawal->canCancelPayout());
        $this->assertTrue($withdrawal->canSyncPayout());
        $this->assertWallet('processing_warning', '0.00', '1000.00');
    }

    public function test_completed_with_stale_diagnostic_finalizes_once_without_active_attention(): void
    {
        $withdrawal = $this->approvedAndDispatched('complete_stale_reason');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $reason = 'Client.Timeout exceeded while awaiting headers';

        $this->sendCallback(
            $this->providerPayload($attempt, 'processing', $reason)
        )->assertOk();
        $completed = $this->providerPayload($attempt, 'completed', $reason);
        $this->sendCallback($completed)->assertOk();
        $this->sendCallback($completed)->assertOk();

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->payout_status);
        $this->assertSame('completed', $withdrawal->gateway_payment_status);
        $this->assertSame($reason, $withdrawal->gateway_fail_reason);
        $this->assertSame($reason, $attempt->fresh()->failure_reason);
        $this->assertSame('Paid', $this->withdrawTableValue('statusLabel', $withdrawal));
        $this->assertSame('success', $this->withdrawTableValue('statusColor', $withdrawal));
        $this->assertNull($this->withdrawTableValue('gatewayFriendlyAlert', $withdrawal));
        $this->assertFalse($this->withdrawTableValue('hasGatewayAttention', $withdrawal));
        $this->assertFalse($this->withdrawTableValue('hasGatewayWarning', $withdrawal));
        $this->assertWallet('complete_stale_reason', '0.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertSame(1, PaymentGatewayEvent::query()
            ->where('withdraw_request_id', $withdrawal->id)
            ->where('event_type', 'withdrawal.gateway_completed')
            ->count());
    }

    public function test_uncertain_submission_becomes_processing_then_completed_through_order_sync(): void
    {
        $withdrawal = $this->pendingWithdrawal('uncertain_sync');
        $admin = $this->approvalAdmin('uncertain-sync-admin', ['ApproveInstantGateway:Withdrawal']);
        $inquiryStatus = null;
        $attempt = null;
        $reason = 'Client.Timeout exceeded while awaiting headers';
        Http::fake(function () use (&$attempt, &$inquiryStatus, $reason) {
            if ($inquiryStatus === null) {
                return Http::failedConnection('Connection timed out');
            }

            return Http::response([
                'result' => 'success',
                'data' => $this->providerPayload($attempt, $inquiryStatus, $reason),
            ]);
        });

        try {
            app(WithdrawService::class)->approveWithInstantGateway($withdrawal, $admin);
            $this->fail('The uncertain provider submission should be reported.');
        } catch (Qc168UncertainSubmissionException) {
            $this->addToAssertionCount(1);
        }

        $withdrawal->refresh();
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->assertSame(WithdrawRequest::PAYOUT_SUBMITTED, $withdrawal->payout_status);
        $this->assertSame(WithdrawalPayoutAttempt::STATUS_CREATE_PENDING, $withdrawal->gateway_payment_status);
        $this->assertSame('Status Needs Confirmation', $this->withdrawTableValue('statusLabel', $withdrawal));
        $this->assertTrue($this->withdrawTableValue('hasGatewayAttention', $withdrawal));
        $this->assertFalse($withdrawal->canRetryPayout());
        $this->assertFalse($withdrawal->canCancelPayout());
        $this->assertTrue($withdrawal->canSyncPayout());
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'event_type' => 'withdrawal.gateway_submission_uncertain',
            'processing_result' => 'submission_uncertain',
        ]);

        $inquiryStatus = 'processing';
        app(Qc168WithdrawalPayoutService::class)->sync($withdrawal);

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_PROCESSING, $withdrawal->payout_status);
        $this->assertSame('Payment Processing', $this->withdrawTableValue('statusLabel', $withdrawal));
        $this->assertFalse($this->withdrawTableValue('hasGatewayAttention', $withdrawal));
        $this->assertTrue($this->withdrawTableValue('hasGatewayWarning', $withdrawal));

        $inquiryStatus = 'completed';
        app(Qc168WithdrawalPayoutService::class)->sync($withdrawal);
        app(Qc168WithdrawalPayoutService::class)->sync($withdrawal->fresh());

        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->fresh()->payout_status);
        $this->assertWallet('uncertain_sync', '0.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
    }

    public function test_manual_unpaid_confirmation_preserves_funds_and_records_provider_evidence(): void
    {
        $withdrawal = $this->uncertainPayout('manual_unpaid');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal']);

        $this->assertTrue($withdrawal->canConfirmGatewayUnpaid());
        $resolved = app(Qc168WithdrawalPayoutService::class)->confirmUnpaid(
            $withdrawal, $admin, $attempt->merchant_order_id,
            'Ticket QC-3344: never paid and will not pay.', 'Provider confirmed closure.', true, '127.0.0.1',
        );

        $this->assertTrue($resolved->canRetryPayout());
        $this->assertTrue($resolved->canCancelPayout());
        $this->assertFalse($resolved->canConfirmGatewayUnpaid());
        $this->assertWallet('manual_unpaid', '0.00', '1000.00');
        $this->assertDatabaseCount('withdrawal_payout_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $attempt->refresh();
        $this->assertTrue($attempt->isManuallyConfirmedUnpaid());
        $this->assertSame('uncertain', data_get($attempt->provider_meta, 'submission_result'));
        $this->assertFalse(data_get($attempt->provider_meta, 'submission_definitive_failure'));
        $this->assertSame($admin->id, data_get($attempt->provider_meta, 'manual_unpaid_confirmation.admin_user_id'));
        $this->assertNotEmpty(data_get($attempt->provider_meta, 'manual_unpaid_confirmation.previous_failure_reason'));
        $event = PaymentGatewayEvent::where('event_type', 'withdrawal.gateway_manually_confirmed_unpaid')->sole();
        $this->assertSame($attempt->merchant_order_id, $event->merchant_order_id);
        $this->assertSame('Ticket QC-3344: never paid and will not pay.', $event->payload['provider_confirmation']);
        $this->assertSame('127.0.0.1', $event->payload['ip']);
    }

    public function test_manually_confirmed_unpaid_can_retry_with_a_new_order_without_another_debit(): void
    {
        $withdrawal = $this->uncertainPayout('manual_retry');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal', 'RetryGateway:Withdrawal']);
        $service = app(Qc168WithdrawalPayoutService::class);
        $resolved = $service->confirmUnpaid($withdrawal, $admin, $attempt->merchant_order_id, 'QC168 confirms no payout now or later.', 'Retry requested.', true);
        Http::swap(new Factory);
        Http::fake(fn () => $this->createOrderResponse());

        $newAttempt = $service->retry($resolved, $admin, 'Submit a new provider order.');

        $this->assertSame(2, $newAttempt->attempt_no);
        $this->assertNotSame($attempt->merchant_order_id, $newAttempt->merchant_order_id);
        $this->assertSame($withdrawal->id, $newAttempt->withdraw_request_id);
        $this->assertTrue($attempt->fresh()->isManuallyConfirmedUnpaid());
        $this->assertWallet('manual_retry', '0.00', '1000.00');
        $this->assertDatabaseCount('wallet_transactions', 1);
        Http::assertNotSent(fn (ClientRequest $request) => str_ends_with($request->url(), '/octopus/api/order/get'));
    }

    public function test_manually_confirmed_unpaid_can_refund_full_reserve_once_and_late_completion_requires_review(): void
    {
        $withdrawal = $this->uncertainPayout('manual_cancel');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal']);
        $service = app(Qc168WithdrawalPayoutService::class);
        $resolved = $service->confirmUnpaid($withdrawal, $admin, $attempt->merchant_order_id, 'QC168 confirms no payout now or later.', 'Return funds.', true);
        Http::fake();
        $service->cancel($resolved, $admin, 'Refund after provider confirmation.');

        $this->assertWallet('manual_cancel', '1000.00', '0.00');
        $this->assertSame('1000.00', WalletTransaction::where('type', 'withdraw_gateway_cancelled')->sole()->amount);
        try {
            $service->cancel($resolved, $admin, 'Duplicate click.');
            $this->fail('A second refund must be blocked.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('wallet_transactions', 2);
        }
        $this->sendCallback($this->providerPayload($attempt, 'completed'))->assertOk();
        $this->assertSame(WithdrawRequest::PAYOUT_REFUND_REVIEW, $resolved->fresh()->payout_status);
        $this->assertWallet('manual_cancel', '1000.00', '0.00');
        Http::assertNothingSent();
    }

    public function test_manual_unpaid_confirmation_requires_permission_evidence_reason_and_acknowledgement(): void
    {
        $withdrawal = $this->uncertainPayout('manual_validation');
        $service = app(Qc168WithdrawalPayoutService::class);
        $admin = $this->approvalAdmin('resolve-admin', ['RetryGateway:Withdrawal']);
        $orderId = $withdrawal->gateway_current_merchant_order_id;
        try {
            $service->confirmUnpaid($withdrawal, $admin, $orderId, 'Evidence', 'Reason', true);
            $this->fail('Retry permission alone must not permit manual confirmation.');
        } catch (AuthorizationException) {
            $this->assertSame(WithdrawRequest::PAYOUT_SUBMITTED, $withdrawal->fresh()->payout_status);
        }
        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal']);
        foreach ([['  ', 'Reason', true], ['Evidence', '  ', true], ['Evidence', 'Reason', false]] as [$evidence, $reason, $acknowledged]) {
            try {
                $service->confirmUnpaid($withdrawal, $admin, $orderId, $evidence, $reason, $acknowledged);
                $this->fail('Incomplete confirmation must be rejected.');
            } catch (ValidationException) {
                $this->assertSame(WithdrawRequest::PAYOUT_SUBMITTED, $withdrawal->fresh()->payout_status);
            }
        }
        $this->assertWallet('manual_validation', '0.00', '1000.00');
        $this->assertSame(0, PaymentGatewayEvent::where('event_type', 'withdrawal.gateway_manually_confirmed_unpaid')->count());
    }

    public function test_manual_unpaid_confirmation_rejects_stale_order_and_completed_or_processing_attempts(): void
    {
        $service = app(Qc168WithdrawalPayoutService::class);
        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal']);
        foreach (['wrong_order', 'processing', 'completed', 'in_flight'] as $scenario) {
            $withdrawal = $this->uncertainPayout('manual_'.$scenario);
            $attempt = $withdrawal->payoutAttempts()->firstOrFail();
            $orderId = $scenario === 'wrong_order' ? 'stale-order' : $attempt->merchant_order_id;
            if (in_array($scenario, ['processing', 'completed'], true)) {
                $this->sendCallback($this->providerPayload($attempt, $scenario))->assertOk();
            } elseif ($scenario === 'in_flight') {
                $attempt->forceFill(['provider_meta' => ['claimed_at' => now()->toAtomString()]])->save();
            }
            try {
                $service->confirmUnpaid($withdrawal, $admin, $orderId, 'Evidence', 'Reason', true);
                $this->fail('A stale or ineligible confirmation must be rejected.');
            } catch (RuntimeException) {
                $this->assertFalse($attempt->fresh()->isManuallyConfirmedUnpaid());
            }
        }
    }

    public function test_provider_processing_update_revokes_manual_confirmation_before_retry_or_refund(): void
    {
        $withdrawal = $this->uncertainPayout('manual_revoked');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal']);
        $service = app(Qc168WithdrawalPayoutService::class);
        $resolved = $service->confirmUnpaid($withdrawal, $admin, $attempt->merchant_order_id, 'Evidence', 'Reason', true);
        $this->sendCallback($this->providerPayload($attempt, 'processing'))->assertOk();

        $this->assertFalse($attempt->fresh()->isManuallyConfirmedUnpaid());
        $this->assertSame(WithdrawRequest::PAYOUT_PROCESSING, $resolved->fresh()->payout_status);
        foreach (['retry', 'cancel'] as $operation) {
            try {
                $service->$operation($resolved, $admin, 'Stale operation.');
                $this->fail('The latest provider status must block this operation.');
            } catch (RuntimeException) {
                $this->assertWallet('manual_revoked', '0.00', '1000.00');
            }
        }
        $this->assertDatabaseCount('withdrawal_payout_attempts', 1);
    }

    public function test_retired_manual_unpaid_action_is_hidden_and_legacy_service_validates_confirmation(): void
    {
        $withdrawal = $this->uncertainPayout('manual_ui');
        $admin = $this->approvalAdmin('resolve-admin', ['ViewAny:WithdrawRequest', 'View:WithdrawRequest', 'CancelGateway:Withdrawal', 'RetryGateway:Withdrawal']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListWithdrawRequests::class)
            ->assertActionHidden(TestAction::make('confirm_gateway_unpaid')->table($withdrawal))
            ->assertActionHidden(TestAction::make('sync_gateway_attention')->table($withdrawal));

        Livewire::test(ViewWithdrawRequest::class, ['record' => $withdrawal->getRouteKey()])
            ->assertActionHidden('confirm_gateway_unpaid')
            ->assertActionHidden('sync_gateway')
            ->assertActionHidden('retry_payout')
            ->assertActionHidden('cancel_payout');
        $data = [
            'merchant_order_id' => $withdrawal->gateway_current_merchant_order_id,
            'provider_confirmation' => 'QC168 ticket: unpaid and will not pay.',
            'reason' => 'Provider confirmed closure.',
            'confirmed_unpaid' => false,
        ];
        $service = app(Qc168WithdrawalPayoutService::class);
        try {
            $service->confirmUnpaid($withdrawal, $admin, $data['merchant_order_id'], $data['provider_confirmation'], $data['reason'], false);
            $this->fail('Legacy confirmation must still require acknowledgement.');
        } catch (ValidationException) {
            $this->assertFalse($withdrawal->fresh()->canCancelPayout());
        }
        $service->confirmUnpaid($withdrawal, $admin, $data['merchant_order_id'], $data['provider_confirmation'], $data['reason'], true);
        Livewire::test(ViewWithdrawRequest::class, ['record' => $withdrawal->getRouteKey()])
            ->assertActionHidden('confirm_gateway_unpaid')
            ->assertActionHidden('retry_payout')
            ->assertActionHidden('cancel_payout');
        $this->assertTrue($withdrawal->fresh()->canCancelPayout());
        $this->assertWallet('manual_ui', '0.00', '1000.00');
    }

    private function uncertainPayout(string $username): WithdrawRequest
    {
        $withdrawal = $this->pendingWithdrawal($username);
        $admin = $this->approvalAdmin('uncertain-approval-admin', ['ApproveInstantGateway:Withdrawal']);
        Http::fake(fn () => Http::failedConnection('Connection timed out'));
        try {
            app(WithdrawService::class)->approveWithInstantGateway($withdrawal, $admin);
            $this->fail('Submission must be uncertain.');
        } catch (Qc168UncertainSubmissionException) {
            return $withdrawal->fresh();
        }
    }

    public function test_manual_unpaid_table_action_stays_hidden_and_legacy_service_records_confirmation_only_once(): void
    {
        $withdrawal = $this->uncertainPayout('manual_table');
        $admin = $this->approvalAdmin('resolve-admin', ['ViewAny:WithdrawRequest', 'View:WithdrawRequest', 'CancelGateway:Withdrawal']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListWithdrawRequests::class)
            ->assertActionHidden(TestAction::make('confirm_gateway_unpaid')->table($withdrawal));
        app(Qc168WithdrawalPayoutService::class)->confirmUnpaid(
            $withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id,
            'QC168 confirms unpaid and no future payment.', 'Resolve timeout.', true,
        );
        Livewire::test(ListWithdrawRequests::class)
            ->assertActionHidden(TestAction::make('cancel_payout')->table($withdrawal))
            ->assertActionHidden(TestAction::make('retry_payout')->table($withdrawal))
            ->assertActionHidden(TestAction::make('sync_gateway')->table($withdrawal));

        try {
            app(Qc168WithdrawalPayoutService::class)->confirmUnpaid(
                $withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'Evidence', 'Duplicate confirmation.', true,
            );
            $this->fail('Confirmation from a stale second session must not be applied twice.');
        } catch (RuntimeException) {
            $this->assertSame(1, PaymentGatewayEvent::where('event_type', 'withdrawal.gateway_manually_confirmed_unpaid')->count());
        }
        $this->assertWallet('manual_table', '0.00', '1000.00');
    }

    public function test_manual_unpaid_action_is_hidden_without_cancel_permission_or_outside_support_scope(): void
    {
        $withdrawal = $this->uncertainPayout('manual_scope');
        $admin = $this->approvalAdmin('resolve-admin', ['ViewAny:WithdrawRequest', 'View:WithdrawRequest', 'RetryGateway:Withdrawal']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewWithdrawRequest::class, ['record' => $withdrawal->getRouteKey()])
            ->assertActionHidden('confirm_gateway_unpaid');

        $admin = $this->approvalAdmin('resolve-admin', ['CancelGateway:Withdrawal']);
        $admin->forceFill(['managed_user_id' => $this->user('unrelated-manager')->id])->save();
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));
        $this->actingAs($admin, 'admin');
        $this->expectException(AuthorizationException::class);
        app(Qc168WithdrawalPayoutService::class)->confirmUnpaid(
            $withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'Evidence', 'Outside scope.', true,
        );
    }

    public function test_failed_reject_and_overdue_never_finalize_or_release_reserve(): void
    {
        $labels = [
            'failed' => 'Payout Failed',
            'reject' => 'Payout Rejected',
            'overdue' => 'Payout Overdue',
        ];

        foreach (['failed', 'reject', 'overdue'] as $status) {
            $username = 'status_'.$status;
            $withdrawal = $this->approvedAndDispatched($username);
            $attempt = $withdrawal->payoutAttempts()->firstOrFail();

            $this->sendCallback($this->providerPayload($attempt, $status))->assertOk();

            $expected = $status === 'reject' ? WithdrawRequest::PAYOUT_REJECTED : $status;
            $withdrawal->refresh();
            $this->assertSame($expected, $withdrawal->payout_status);
            $this->assertSame($status, $withdrawal->gateway_payment_status);
            $this->assertSame($status, $withdrawal->gateway_fail_reason);
            $this->assertSame($labels[$status], $this->withdrawTableValue('statusLabel', $withdrawal));
            $this->assertTrue($this->withdrawTableValue('hasGatewayAttention', $withdrawal));
            $this->assertFalse($this->withdrawTableValue('hasGatewayWarning', $withdrawal));
            $this->assertTrue($withdrawal->canRetryPayout());
            $this->assertTrue($withdrawal->canCancelPayout());
            $this->assertWallet($username, '0.00', '1000.00');
            $this->assertSame(1, WalletTransaction::query()
                ->where('reference_type', WithdrawRequest::class)
                ->where('reference_id', $withdrawal->id)
                ->count());
        }
    }

    public function test_retry_syncs_old_order_and_creates_new_immutable_attempt(): void
    {
        $withdrawal = $this->approvedAndDispatched('retry');
        $oldAttempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($oldAttempt, 'reject'))->assertOk();

        $this->inquiryStatus = 'reject';

        $newAttempt = app(Qc168WithdrawalPayoutService::class)->retry(
            $withdrawal,
            $this->admin(),
            'Provider rejected the first attempt.',
        );

        $this->assertNotNull($newAttempt);
        $this->assertSame(2, $withdrawal->payoutAttempts()->count());
        $this->assertNotSame($oldAttempt->merchant_order_id, $newAttempt->merchant_order_id);
        $this->assertSame('reject', $oldAttempt->fresh()->gateway_status);
        $this->assertSame(1, $oldAttempt->attempt_no);
        $this->assertSame(2, $newAttempt->attempt_no);
    }

    public function test_retry_does_not_create_new_attempt_when_sync_finds_completed(): void
    {
        $withdrawal = $this->approvedAndDispatched('retry_complete');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($attempt, 'reject'))->assertOk();

        $this->inquiryStatus = 'completed';

        $newAttempt = app(Qc168WithdrawalPayoutService::class)->retry(
            $withdrawal,
            $this->admin(),
            'Retry after provider rejection.',
        );

        $this->assertNull($newAttempt);
        $this->assertSame(1, $withdrawal->payoutAttempts()->count());
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal->fresh()->payout_status);
        $this->assertWallet('retry_complete', '0.00', '0.00');
    }

    public function test_cancel_verifies_final_failure_then_releases_reserve_once(): void
    {
        $withdrawal = $this->approvedAndDispatched('cancel');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($attempt, 'failed'))->assertOk();

        $this->inquiryStatus = 'failed';

        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal,
            $this->admin(),
            'Confirmed failed with provider.',
        );

        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->fresh()->payout_status);
        $this->assertWallet('cancel', '1000.00', '0.00');
        $this->assertSame(2, WalletTransaction::query()
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());

        $this->expectException(RuntimeException::class);
        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal->fresh(),
            $this->admin(),
            'Duplicate cancellation.',
        );
    }

    public function test_cancelled_withdrawal_remains_cancelled_after_later_reject_callback_and_cannot_refund_again(): void
    {
        $withdrawal = $this->cancelledCreateFailure('cancel_late_reject');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();

        $this->sendCallback($this->providerPayload($attempt, 'reject'))->assertOk();

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->payout_status);
        $this->assertSame(WithdrawalPayoutAttempt::STATUS_CREATE_FAILED, $withdrawal->gateway_payment_status);
        $this->assertNull($withdrawal->gateway_last_callback_at);
        $this->assertSame('reject', $attempt->fresh()->gateway_status);
        $this->assertFalse($withdrawal->canCancelPayout());
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'withdrawal_payout_attempt_id' => $attempt->id,
            'event_type' => 'withdrawal.gateway_rejected',
            'processing_result' => 'status_recorded_after_cancel',
        ]);

        $secondWithdrawal = app(WithdrawService::class)->submit($withdrawal->user, '1000.00');
        Http::fake(fn () => Http::response([
            'result' => 'success',
            'data' => $this->providerPayload($attempt, 'reject'),
        ]));

        try {
            app(Qc168WithdrawalPayoutService::class)->cancel(
                $withdrawal,
                $this->admin(),
                'Duplicate cancellation after late reject.',
            );
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertWallet('cancel_late_reject', '0.00', '1000.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertSame(WithdrawRequest::STATUS_PENDING, $secondWithdrawal->fresh()->status);
    }

    public function test_cancelled_withdrawal_remains_cancelled_after_later_failed_sync(): void
    {
        $withdrawal = $this->cancelledCreateFailure('cancel_late_failed');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->inquiryStatus = 'failed';

        app(Qc168WithdrawalPayoutService::class)->sync($withdrawal);

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->payout_status);
        $this->assertSame(WithdrawalPayoutAttempt::STATUS_CREATE_FAILED, $withdrawal->gateway_payment_status);
        $this->assertNull($withdrawal->gateway_last_synced_at);
        $this->assertSame('failed', $attempt->fresh()->gateway_status);
        $this->assertWallet('cancel_late_failed', '1000.00', '0.00');
    }

    public function test_cancelled_withdrawal_remains_cancelled_after_later_overdue_callback(): void
    {
        $withdrawal = $this->cancelledCreateFailure('cancel_late_overdue');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();

        $this->sendCallback($this->providerPayload($attempt, 'overdue'))->assertOk();

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->payout_status);
        $this->assertSame(WithdrawalPayoutAttempt::STATUS_CREATE_FAILED, $withdrawal->gateway_payment_status);
        $this->assertNull($withdrawal->gateway_last_callback_at);
        $this->assertSame('overdue', $attempt->fresh()->gateway_status);
        $this->assertWallet('cancel_late_overdue', '1000.00', '0.00');
    }

    public function test_cancellation_ledger_blocks_a_second_refund_even_if_local_status_was_regressed(): void
    {
        $withdrawal = $this->cancelledCreateFailure('cancel_ledger_guard');
        $cancellation = WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->firstOrFail();
        $this->assertSame('withdrawal_gateway_cancelled:'.$withdrawal->id, $cancellation->idempotency_key);
        $cancellation->forceFill(['idempotency_key' => null])->save();
        $withdrawal->forceFill(['payout_status' => WithdrawRequest::PAYOUT_REJECTED])->save();
        $secondWithdrawal = app(WithdrawService::class)->submit($withdrawal->user, '1000.00');

        $this->assertFalse($withdrawal->fresh()->canCancelPayout());
        $this->assertFalse(WithdrawRequest::query()->whereKey($withdrawal->id)->withReservedFunds()->exists());

        try {
            app(WithdrawService::class)->cancelPayout(
                $withdrawal->fresh(),
                $withdrawal->payoutAttempts()->firstOrFail(),
                $this->admin(),
                'Must not release twice.',
            );
            $this->fail('The cancellation ledger must block a second refund.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertWallet('cancel_ledger_guard', '0.00', '1000.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertSame(WithdrawRequest::STATUS_PENDING, $secondWithdrawal->fresh()->status);
    }

    public function test_concurrent_cancel_calls_create_one_refund(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Concurrent cancellation requires the shared MySQL test database.');
        }

        $withdrawal = $this->explicitlyRejectedPayout('cancel_concurrent');
        $admin = $this->admin();
        $code = sprintf(
            "try { app(%s::class)->cancel(%s::findOrFail(%d), %s::findOrFail(%d), 'Concurrent cancellation.'); echo 'CANCELLED'; } catch (\\RuntimeException) { echo 'BLOCKED'; }",
            Qc168WithdrawalPayoutService::class,
            WithdrawRequest::class,
            $withdrawal->id,
            AdminUser::class,
            $admin->id,
        );
        $processes = [
            new Process([PHP_BINARY, base_path('artisan'), 'tinker', '--execute', $code], base_path()),
            new Process([PHP_BINARY, base_path('artisan'), 'tinker', '--execute', $code], base_path()),
        ];

        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();
        }

        $this->assertSame(1, collect($processes)->filter(
            fn (Process $process): bool => str_contains($process->getOutput(), 'CANCELLED')
        )->count());
        $this->assertSame(1, collect($processes)->filter(
            fn (Process $process): bool => str_contains($process->getOutput(), 'BLOCKED')
        )->count());
        $this->assertWallet('cancel_concurrent', '1000.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
    }

    public function test_completed_after_cancellation_routes_to_financial_review_without_finalizing_again(): void
    {
        $withdrawal = $this->cancelledCreateFailure('cancel_late_completed');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();

        $this->sendCallback($this->providerPayload($attempt, 'completed'))->assertOk();

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_REFUND_REVIEW, $withdrawal->payout_status);
        $this->assertSame('completed', $withdrawal->gateway_payment_status);
        $this->assertSame('completed', $attempt->fresh()->gateway_status);
        $this->assertFalse($withdrawal->canCancelPayout());
        $this->assertFalse(WithdrawRequest::query()->whereKey($withdrawal->id)->withReservedFunds()->exists());
        $this->assertWallet('cancel_late_completed', '1000.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'withdrawal_payout_attempt_id' => $attempt->id,
            'event_type' => 'withdrawal.gateway_completed_after_cancel',
            'processing_result' => 'financial_review_required',
        ]);
        $this->assertStringContainsString(
            'financial review',
            strtolower($this->withdrawTableValue('gatewayFriendlyAlert', $withdrawal)),
        );
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => $withdrawal->id,
            'withdrawal_payout_attempt_id' => $attempt->id,
            'event_type' => 'gateway.withdrawal.callback.create_rejected_contradiction',
            'processing_result' => 'financial_review_required',
        ]);
    }

    public function test_completed_old_attempt_after_cancellation_also_routes_to_financial_review(): void
    {
        $withdrawal = $this->approvedAndDispatched('cancel_old_attempt_completed');
        $oldAttempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($oldAttempt, 'reject'))->assertOk();
        $this->inquiryStatus = 'reject';
        $currentAttempt = app(Qc168WithdrawalPayoutService::class)->retry(
            $withdrawal,
            $this->admin(),
            'Retry before cancellation.',
        );
        $this->sendCallback($this->providerPayload($currentAttempt, 'failed'))->assertOk();
        $this->inquiryStatus = 'failed';
        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal->fresh(),
            $this->admin(),
            'Cancel current failed attempt.',
        );

        $this->sendCallback($this->providerPayload($oldAttempt, 'completed'))->assertOk();

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_REFUND_REVIEW, $withdrawal->payout_status);
        $this->assertSame('completed', $oldAttempt->fresh()->gateway_status);
        $this->assertSame($oldAttempt->id, data_get($withdrawal->gateway_meta, 'financial_review_attempt_id'));
        $this->assertWallet('cancel_old_attempt_completed', '1000.00', '0.00');
        $this->assertSame(1, WalletTransaction::query()
            ->where('type', 'withdraw_gateway_cancelled')
            ->where('reference_type', WithdrawRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->count());
    }

    public function test_delayed_create_response_after_cancellation_does_not_regress_local_state(): void
    {
        $withdrawal = $this->approvedAndDispatched('cancel_delayed_create_response');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($attempt, 'failed'))->assertOk();
        $this->inquiryStatus = 'failed';
        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal,
            $this->admin(),
            'Cancel before delayed create response.',
        );
        $this->inquiryStatus = null;

        $method = new \ReflectionMethod(Qc168WithdrawalPayoutService::class, 'submitAttempt');
        $method->setAccessible(true);
        $method->invoke(app(Qc168WithdrawalPayoutService::class), $attempt, 'delayed_create_response');

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->payout_status);
        $this->assertSame('failed', $withdrawal->gateway_payment_status);
        $this->assertSame('pending', $attempt->fresh()->gateway_status);
        $this->assertWallet('cancel_delayed_create_response', '1000.00', '0.00');
    }

    public function test_delayed_submission_failure_after_cancellation_does_not_regress_local_state(): void
    {
        $withdrawal = $this->approvedAndDispatched('cancel_delayed_create_failure');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($attempt, 'failed'))->assertOk();
        $this->inquiryStatus = 'failed';
        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal,
            $this->admin(),
            'Cancel before delayed create failure.',
        );

        $method = new \ReflectionMethod(Qc168WithdrawalPayoutService::class, 'recordSubmissionFailure');
        $method->setAccessible(true);
        $method->invoke(
            app(Qc168WithdrawalPayoutService::class),
            $attempt,
            new RuntimeException('Delayed create request failed.'),
            true,
            'delayed_create_failure',
        );

        $withdrawal->refresh();
        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, $withdrawal->payout_status);
        $this->assertSame('failed', $withdrawal->gateway_payment_status);
        $this->assertSame(WithdrawalPayoutAttempt::STATUS_CREATE_FAILED, $attempt->fresh()->gateway_status);
        $this->assertWallet('cancel_delayed_create_failure', '1000.00', '0.00');
    }

    public function test_processing_attempt_cannot_be_cancelled(): void
    {
        $withdrawal = $this->approvedAndDispatched('processing_cancel');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->sendCallback($this->providerPayload($attempt, 'processing'))->assertOk();

        $this->expectException(RuntimeException::class);
        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal,
            $this->admin(),
            'Unsafe cancellation.',
        );
    }

    public function test_missing_bank_code_does_not_block_withdrawal_submission(): void
    {
        $user = $this->user('missing_bank');
        UserBankAccount::query()->create([
            'user_id' => $user->id,
            'bank_account_name' => 'Nguyen Hoa',
            'bank_name' => 'Unsupported Bank',
            'bank_code' => null,
            'bank_account_no' => '9988776655',
            'status' => UserBankAccount::STATUS_ACTIVE,
        ]);

        $withdrawal = app(WithdrawService::class)->submit($user, '1000.00');

        $this->assertSame(WithdrawRequest::STATUS_PENDING, $withdrawal->status);
        $this->assertNull($withdrawal->bank_code);
        $this->assertWallet('missing_bank', '0.00', '1000.00');
    }

    public function test_approval_blocks_a_legacy_request_without_a_deterministic_bank_code(): void
    {
        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, false);
        $user = $this->user('legacy_bank');
        UserBankAccount::query()->create([
            'user_id' => $user->id,
            'bank_account_name' => 'Nguyen Hoa',
            'bank_name' => 'Unsupported Bank',
            'bank_code' => null,
            'bank_account_no' => '9988776656',
            'status' => UserBankAccount::STATUS_ACTIVE,
        ]);
        $withdrawal = app(WithdrawService::class)->submit($user, '1000.00');

        Setting::setValue(Qc168Settings::KEY_WITHDRAW_ENABLED, true);

        try {
            app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());
            $this->fail('Approval should have been blocked for an unmapped bank.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bank_account', $exception->errors());
        }

        $this->assertSame(WithdrawRequest::STATUS_PENDING, $withdrawal->fresh()->status);
        $this->assertWallet('legacy_bank', '0.00', '1000.00');
        $this->assertDatabaseCount('withdrawal_payout_attempts', 0);
    }

    public function test_bank_details_remain_the_request_snapshot_after_account_changes(): void
    {
        $withdrawal = $this->pendingWithdrawal('snapshot');
        $withdrawal->bankAccount()->update([
            'bank_name' => 'MBBank',
            'bank_code' => '970422',
            'bank_account_name' => 'Changed Holder',
            'bank_account_no' => '7777777777',
        ]);

        app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());
        $withdrawal->forceFill(['gateway_release_at' => now()])->save();

        Http::fake(function (ClientRequest $request) {
            $this->assertSame('970436', $request['to_bank_name']);
            $this->assertSame('Nguyen Hoa', $request['to_name']);
            $this->assertNotSame('7777777777', $request['to']);

            return $this->createOrderResponse();
        });

        app(Qc168WithdrawalPayoutService::class)->dispatch($withdrawal);

        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $this->assertSame('Vietcombank', $attempt->bank_name_snapshot);
        $this->assertSame('970436', $attempt->bank_code_snapshot);
        $this->assertSame('Nguyen Hoa', $attempt->bank_account_name_snapshot);
        $this->assertSame($withdrawal->bank_account_no, $attempt->bank_account_no_snapshot);
    }

    public function test_api_formatter_exposes_payout_lifecycle_without_gateway_secrets(): void
    {
        $withdrawal = $this->pendingWithdrawal('api_fields');
        app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());
        $withdrawal->forceFill([
            'gateway_fail_reason' => 'CallserverErr: http://10.40.4.126:8000 Client.Timeout',
        ])->save();

        $formatted = ApiFormatter::withdrawRequest($withdrawal->fresh());

        $this->assertSame(WithdrawRequest::PAYOUT_WAITING_RELEASE, $formatted['payout_status']);
        $this->assertNotNull($formatted['payout_status_label']);
        $this->assertNotNull($formatted['gateway_release_at']);
        $this->assertArrayNotHasKey('oc_key', $formatted);
        $this->assertArrayNotHasKey('secret', $formatted);
        $this->assertArrayNotHasKey('merchant_uid', $formatted);
        $this->assertArrayNotHasKey('gateway_fail_reason', $formatted);
        $this->assertStringNotContainsString('10.40.4.126', json_encode($formatted));
    }

    public function test_gateway_audit_payloads_strip_credentials_recursively(): void
    {
        $event = PaymentGatewayEvent::query()->create([
            'provider' => WithdrawRequest::GATEWAY_QC168,
            'event_type' => 'withdrawal.gateway_sync',
            'signature_valid' => true,
            'payload' => [
                'OC-Key' => 'must-not-be-stored',
                'safe' => 'retained',
                'nested' => [
                    'qc168_secret' => 'must-not-be-stored',
                    'payment_status' => 'processing',
                ],
            ],
            'received_at' => now(),
        ]);

        $payload = $event->fresh()->payload;

        $this->assertSame('retained', $payload['safe']);
        $this->assertSame('processing', $payload['nested']['payment_status']);
        $this->assertArrayNotHasKey('OC-Key', $payload);
        $this->assertArrayNotHasKey('qc168_secret', $payload['nested']);

        try {
            $event->forceFill(['processing_result' => 'tampered'])->save();
            $this->fail('Gateway audit events must not be updated.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }

        try {
            $event->delete();
            $this->fail('Gateway audit events must not be deleted.');
        } catch (\LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('payment_gateway_events', [
            'id' => $event->id,
            'processing_result' => null,
        ]);
    }

    public function test_callback_validation_rejects_tampered_amount_without_finalization(): void
    {
        $withdrawal = $this->approvedAndDispatched('tampered');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $payload = $this->providerPayload($attempt, 'completed');
        $payload['amount'] = '1.00';

        $this->sendCallback($payload)->assertStatus(422);

        $this->assertSame(WithdrawRequest::PAYOUT_PENDING, $withdrawal->fresh()->payout_status);
        $this->assertWallet('tampered', '0.00', '1000.00');
    }

    #[DataProvider('manualSettlementFailures')]
    public function test_failed_gateway_payout_can_be_settled_manually_once(string $status): void
    {
        $withdrawal = $status === 'create_failed'
            ? $this->explicitlyRejectedPayout('settled')
            : $this->approvedAndDispatched('settled');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        if ($status !== 'create_failed') {
            $this->sendCallback($this->providerPayload($attempt, $status))->assertOk();
            $this->inquiryStatus = $status;
        }
        $withdrawal->refresh();
        $originalMode = $withdrawal->approval_mode;
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        $sentBefore = Http::recorded()->count();
        $service = app(WithdrawService::class);
        $service->markFailedPayoutPaid($withdrawal, $admin, $attempt->merchant_order_id, 'BANK-123', 'Paid by bank transfer.', true, '192.0.2.1');

        $withdrawal->refresh();
        $this->assertTrue($withdrawal->isPayoutCompleted());
        $this->assertTrue($withdrawal->isManuallySettledPayout());
        $this->assertSame($originalMode, $withdrawal->approval_mode);
        $this->assertSame('qc168', $withdrawal->gateway_provider);
        $this->assertSame($status, $attempt->fresh()->gateway_status);
        $this->assertSame($status, $withdrawal->gateway_payment_status);
        $this->assertNull($withdrawal->gateway_completed_at);
        $this->assertSame('BANK-123', data_get($withdrawal->gateway_meta, 'manual_payout.payment_reference'));
        $this->assertSame('manual_after_failure', $this->withdrawTableValue('handlingState', $withdrawal));
        $this->assertFalse($withdrawal->canRetryPayout());
        $this->assertFalse($withdrawal->canCancelPayout());
        $this->assertFalse($withdrawal->canSyncPayout());
        $this->assertFalse($withdrawal->canRevertManualApproval());
        $this->assertFalse(WithdrawRequest::withReservedFunds()->whereKey($withdrawal->id)->exists());
        $this->assertDatabaseCount('withdrawal_payout_attempts', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertWallet('settled', '0.00', '0.00');
        $this->assertCount($sentBefore + ($status === 'create_failed' ? 0 : 1), Http::recorded());
        $event = PaymentGatewayEvent::where('event_type', 'withdrawal.failed_payout_manually_completed')->sole();
        $this->assertSame($admin->id, $event->actor_admin_user_id);
        $this->assertSame('1000.00', data_get($event->payload, 'reserved_amount_consumed'));
        $this->assertSame('980.00', data_get($event->payload, 'user_payout_amount'));

        try {
            $service->markFailedPayoutPaid($withdrawal, $admin, $attempt->merchant_order_id, 'BANK-123', 'Repeated click.', true);
            $this->fail('A repeated settlement must be rejected.');
        } catch (RuntimeException) {
            $this->assertWallet('settled', '0.00', '0.00');
            $this->assertSame(1, PaymentGatewayEvent::where('event_type', 'withdrawal.failed_payout_manually_completed')->count());
        }
    }

    public static function manualSettlementFailures(): array
    {
        return [['create_failed'], ['failed'], ['reject'], ['overdue']];
    }

    public function test_manual_settlement_rechecks_provider_and_rejects_processing_completed_and_invalid_replies(): void
    {
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        foreach (['processing', 'completed', 'refund', 'invalid'] as $status) {
            $withdrawal = $this->approvedAndDispatched('check-'.$status);
            $attempt = $withdrawal->payoutAttempts()->firstOrFail();
            $this->sendCallback($this->providerPayload($attempt, 'failed'))->assertOk();
            $this->inquiryStatus = $status;
            try {
                app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $attempt->merchant_order_id, 'BANK-123', 'Already paid.', true);
                $this->fail('An unsafe provider reply must block manual settlement.');
            } catch (RuntimeException) {
                $this->assertFalse($withdrawal->fresh()->isManuallySettledPayout());
                $this->assertWallet('check-'.$status, '0.00', $status === 'completed' ? '0.00' : '1000.00');
            }
        }
        $this->assertSame(0, PaymentGatewayEvent::where('event_type', 'withdrawal.failed_payout_manually_completed')->count());
    }

    public function test_manual_settlement_requires_permission_scope_evidence_and_current_order(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('settlement-guards');
        $admin = $this->approvalAdmin('settlement-admin', []);
        $service = app(WithdrawService::class);
        try {
            $service->markFailedPayoutPaid($withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'BANK-123', 'Paid.', true);
            $this->fail('Manual approval permission is required.');
        } catch (AuthorizationException) {
            $this->assertWallet('settlement-guards', '0.00', '1000.00');
        }
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        foreach ([[' ', 'Paid.', true], ['BANK-123', ' ', true], ['BANK-123', 'Paid.', false]] as [$reference, $reason, $confirmed]) {
            try {
                $service->markFailedPayoutPaid($withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, $reference, $reason, $confirmed);
                $this->fail('Evidence and confirmation are required.');
            } catch (ValidationException) {
                $this->assertWallet('settlement-guards', '0.00', '1000.00');
            }
        }
        try {
            $service->markFailedPayoutPaid($withdrawal, $admin, 'STALE-ORDER', 'BANK-123', 'Paid.', true);
            $this->fail('A stale order must not be settled.');
        } catch (RuntimeException) {
            $this->assertWallet('settlement-guards', '0.00', '1000.00');
        }
        $admin->forceFill(['managed_user_id' => $this->user('unrelated-settlement-manager')->id])->save();
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));
        $this->actingAs($admin, 'admin');
        $this->expectException(AuthorizationException::class);
        $service->markFailedPayoutPaid($withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'BANK-123', 'Paid.', true);
    }

    public function test_manual_settlement_rejects_refunded_or_unresolved_orders(): void
    {
        $withdrawal = $this->cancelledCreateFailure('settlement-refunded');
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        // The refund ledger must block settlement even if a stale state says failed.
        $withdrawal->forceFill(['payout_status' => WithdrawRequest::PAYOUT_CREATE_FAILED])->save();
        try {
            app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'BANK-123', 'Paid.', true);
            $this->fail('Refunded funds cannot be consumed.');
        } catch (RuntimeException) {
            $this->assertWallet('settlement-refunded', '1000.00', '0.00');
        }
        $withdrawal = $this->uncertainPayout('settlement-uncertain');
        $this->assertFalse($withdrawal->canMarkPayoutPaid());
        $this->expectException(RuntimeException::class);
        app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'BANK-123', 'Paid.', true);
    }

    public function test_callbacks_after_manual_settlement_never_reopen_or_consume_another_reserve(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('settlement-callback');
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $attempt->merchant_order_id, 'BANK-123', 'Paid.', true);
        $withdrawal->user->wallets()->where('type', Wallet::TYPE_WITHDRAWABLE)->update(['locked_balance' => 500]);

        foreach (['failed', 'processing', 'refund', 'completed', 'completed', 'failed'] as $status) {
            $this->sendCallback($this->providerPayload($attempt, $status))->assertOk();
            $this->assertWallet('settlement-callback', '0.00', '500.00');
            $this->assertTrue($withdrawal->fresh()->isPayoutCompleted());
            $this->assertFalse($withdrawal->fresh()->canRetryPayout());
        }
        $this->assertSame('review', $this->withdrawTableValue('handlingState', $withdrawal->fresh()));
        $this->assertSame('completed', $attempt->fresh()->gateway_status);
        $this->assertSame('provider_completed_after_manual_payout', data_get($withdrawal->fresh()->gateway_meta, 'financial_review_reason'));
        $this->assertSame(0, PaymentGatewayEvent::where('event_type', 'withdrawal.gateway_completed')->count());
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_delayed_submission_results_preserve_manual_settlement(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('settlement-delayed', retrySucceeds: true);
        $attempt = $withdrawal->payoutAttempts()->firstOrFail();
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $attempt->merchant_order_id, 'BANK-123', 'Paid.', true);

        $service = app(Qc168WithdrawalPayoutService::class);
        $method = new \ReflectionMethod($service, 'submitAttempt');
        $method->invoke($service, $attempt, 'delayed_create_response');
        $this->assertTrue($withdrawal->fresh()->isPayoutCompleted());
        $this->assertWallet('settlement-delayed', '0.00', '0.00');

        $method = new \ReflectionMethod($service, 'recordSubmissionFailure');
        $method->invoke($service, $attempt, new RuntimeException('Delayed failure'), true, 'delayed_create_failure');
        $this->assertTrue($withdrawal->fresh()->isPayoutCompleted());
        $this->assertWallet('settlement-delayed', '0.00', '0.00');
        $this->assertSame(2, PaymentGatewayEvent::where('processing_result', 'status_recorded_after_manual_payout')->count());
    }

    public function test_manual_settlement_rolls_back_when_audit_fails(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('settlement-rollback');
        $admin = $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        $this->mock(PaymentGatewayEventRecorder::class)->shouldReceive('recordWithdrawal')->once()->andThrow(new RuntimeException('Audit unavailable'));
        try {
            app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $withdrawal->gateway_current_merchant_order_id, 'BANK-123', 'Paid.', true);
            $this->fail('Audit failure must roll back settlement.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
            $this->assertWallet('settlement-rollback', '0.00', '1000.00');
            $this->assertFalse($withdrawal->fresh()->isManuallySettledPayout());
            $this->assertSame(WithdrawRequest::PAYOUT_CREATE_FAILED, $withdrawal->fresh()->payout_status);
        }
    }

    public function test_retired_manual_settlement_action_stays_hidden_even_with_permission(): void
    {
        $withdrawal = $this->explicitlyRejectedPayout('settlement-ui');
        $admin = $this->approvalAdmin('settlement-admin', ['ViewAny:WithdrawRequest', 'View:WithdrawRequest']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListWithdrawRequests::class)->assertActionHidden(TestAction::make('mark_failed_payout_paid')->table($withdrawal));
        $this->approvalAdmin('settlement-admin', ['ApproveManual:Withdrawal']);
        $admin->refresh();
        Livewire::test(ListWithdrawRequests::class)->assertActionHidden(TestAction::make('mark_failed_payout_paid')->table($withdrawal));
        Livewire::test(ViewWithdrawRequest::class, ['record' => $withdrawal->id])
            ->assertActionHidden('mark_failed_payout_paid');
        $data = ['merchant_order_id' => $withdrawal->gateway_current_merchant_order_id, 'payment_reference' => 'BANK-123', 'reason' => 'Paid manually.', 'confirmed_paid' => false];
        app(WithdrawService::class)->markFailedPayoutPaid($withdrawal, $admin, $data['merchant_order_id'], $data['payment_reference'], $data['reason'], true);
        Livewire::test(ViewWithdrawRequest::class, ['record' => $withdrawal->id])
            ->assertActionHidden('mark_failed_payout_paid')
            ->assertActionHidden('retry_payout')->assertActionHidden('cancel_payout')
            ->assertSee('BANK-123');
        $this->assertTrue($withdrawal->fresh()->isManuallySettledPayout());
    }

    private function pendingWithdrawal(string $username): WithdrawRequest
    {
        $user = $this->user($username);
        UserBankAccount::query()->create([
            'user_id' => $user->id,
            'bank_account_name' => 'Nguyen Hoa',
            'bank_name' => 'Vietcombank',
            'bank_code' => '970436',
            'bank_account_no' => (string) (1000000000 + $user->id),
            'status' => UserBankAccount::STATUS_ACTIVE,
        ]);

        return app(WithdrawService::class)->submit($user, '1000.00');
    }

    private function explicitlyRejectedPayout(string $username, bool $retrySucceeds = false): WithdrawRequest
    {
        $withdrawal = $this->pendingWithdrawal($username);
        $admin = $this->approvalAdmin("{$username}-admin", ['ApproveInstantGateway:Withdrawal']);
        $requestCount = 0;
        Http::fake(function (ClientRequest $request) use (&$requestCount, $retrySucceeds) {
            if (str_ends_with($request->url(), '/octopus/api/order/get') && $this->inquiryStatus !== null) {
                $attempt = WithdrawalPayoutAttempt::query()
                    ->where('merchant_order_id', (string) $request['merchant_order_id'])
                    ->firstOrFail();

                return Http::response([
                    'result' => 'success',
                    'data' => $this->providerPayload($attempt, $this->inquiryStatus),
                ]);
            }

            $requestCount++;

            if ($retrySucceeds && $requestCount > 1) {
                return $this->createOrderResponse();
            }

            return Http::response([
                'result' => 'error',
                'data' => 'QC168 did not create the payout order',
            ], 422);
        });

        try {
            app(WithdrawService::class)->approveWithInstantGateway($withdrawal, $admin);
            $this->fail('The provider should explicitly reject order creation.');
        } catch (Qc168OrderRejectedException) {
            $this->addToAssertionCount(1);
        }

        return $withdrawal->fresh();
    }

    private function cancelledCreateFailure(string $username): WithdrawRequest
    {
        $withdrawal = $this->explicitlyRejectedPayout($username);

        app(Qc168WithdrawalPayoutService::class)->cancel(
            $withdrawal,
            $this->admin(),
            'Confirmed create failure.',
        );

        return $withdrawal->fresh();
    }

    private function approvedAndDispatched(string $username): WithdrawRequest
    {
        $withdrawal = $this->pendingWithdrawal($username);
        app(WithdrawService::class)->approveWithScheduledGateway($withdrawal, $this->admin());
        $withdrawal->forceFill(['gateway_release_at' => now()])->save();
        Http::fake(function (ClientRequest $request) {
            if (str_ends_with($request->url(), '/octopus/api/order/get') && $this->inquiryStatus !== null) {
                $attempt = WithdrawalPayoutAttempt::query()
                    ->where('merchant_order_id', (string) $request['merchant_order_id'])
                    ->firstOrFail();

                return Http::response([
                    'result' => 'success',
                    'data' => $this->providerPayload($attempt, $this->inquiryStatus),
                ]);
            }

            return $this->createOrderResponse();
        });
        app(Qc168WithdrawalPayoutService::class)->dispatch($withdrawal);

        return $withdrawal->fresh();
    }

    private function createOrderResponse()
    {
        return Http::response([
            'result' => 'success',
            'data' => [
                'payment' => [
                    'payment_status' => 'pending',
                    'payment_uid' => 'PAY-'.uniqid(),
                    'currency' => 'vnd',
                ],
            ],
        ]);
    }

    private function providerPayload(
        WithdrawalPayoutAttempt $attempt,
        string $status,
        ?string $failureReason = null,
    ): array {
        return [
            'transaction_type' => 'expense',
            'accounting_type' => 'pay',
            'merchant_uid' => 'merchant-123',
            'amount' => $attempt->amount,
            'final_amount' => $attempt->amount,
            'currency' => 'VND',
            'payment' => 'vn02',
            'notify_url' => route('payment-gateway.qc168.withdrawal.callback'),
            'merchant_order_id' => $attempt->merchant_order_id,
            'payment_status' => $status,
            'payment_fail_reason' => $failureReason
                ?? (in_array($status, ['failed', 'reject', 'overdue'], true) ? $status : ''),
            'order_uid' => 'ORDER-'.$attempt->id,
            'payment_uid' => 'PAY-'.$attempt->id,
            'signture_key' => 'callback-key',
            'tenant' => 'tenant-1',
        ];
    }

    private function sendCallback(array $payload)
    {
        return $this->postJson(
            '/payment-gateway/qc168/withdrawal/callback',
            $payload,
            ['Oc-Signature' => app(Qc168Gateway::class)->sign($payload)],
        );
    }

    private function withdrawTableValue(
        string $method,
        WithdrawRequest $withdrawal,
    ): mixed {
        $reflection = new \ReflectionMethod(WithdrawRequestsTable::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $withdrawal);
    }

    private function user(string $username): User
    {
        $user = User::query()->create([
            'name' => str_replace('_', ' ', $username),
            'username' => $username,
            'password' => Hash::make('password'),
            'status' => 'active',
            'withdraw_status' => true,
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
            'balance' => '1000.00',
            'locked_balance' => 0,
        ]);

        return $user;
    }

    private function admin(): AdminUser
    {
        return $this->approvalAdmin('payout-admin', ['ApproveScheduledGateway:Withdrawal']);
    }

    private function approvalAdmin(string $username, array $permissions): AdminUser
    {
        $admin = AdminUser::query()->firstOrCreate(
            ['username' => $username],
            ['password' => Hash::make('password'), 'status' => 'active'],
        );

        foreach ($permissions as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }

        return $admin;
    }

    private function assertWallet(string $username, string $balance, string $lockedBalance): void
    {
        $user = User::query()->where('username', $username)->firstOrFail();
        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->firstOrFail();

        $this->assertSame($balance, $wallet->balance);
        $this->assertSame($lockedBalance, $wallet->locked_balance);
    }
}
