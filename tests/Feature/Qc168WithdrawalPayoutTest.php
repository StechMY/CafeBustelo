<?php

namespace Tests\Feature;

use App\Exceptions\Qc168OrderRejectedException;
use App\Exceptions\Qc168UncertainSubmissionException;
use App\Filament\Resources\WithdrawRequests\Tables\WithdrawRequestsTable;
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
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PaymentGateway\Qc168WithdrawalPayoutService;
use App\Services\Settings\Qc168Settings;
use App\Services\WithdrawService;
use App\Support\Api\ApiFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PDO;
use RuntimeException;
use Spatie\Permission\Models\Permission;
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
        Http::fake(function () use (&$requestCount, $retrySucceeds) {
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
