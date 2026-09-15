<?php

namespace Tests\Feature;

use App\Filament\Resources\RechargeRequests\Tables\RechargeRequestsTable;
use App\Models\PaymentGatewayEvent;
use App\Models\PointTransaction;
use App\Models\RechargeMethod;
use App\Models\RechargeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PaymentGateway\Qc168BalanceService;
use App\Services\PaymentGateway\Qc168CallbackService;
use App\Services\PaymentGateway\Qc168Gateway;
use App\Services\PointService;
use App\Services\RechargeApprovalService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Qc168GatewayTest extends TestCase
{
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
            'services.qc168.payment' => 'merchant-channel',
        ]);

        $this->artisan('migrate:fresh')->run();
    }

    public function test_api_creates_local_gateway_request_before_calling_qc168(): void
    {
        $user = $this->user('gateway_user');
        Sanctum::actingAs($user);

        Http::fake(function (ClientRequest $request) use ($user) {
            $this->assertDatabaseHas('recharge_requests', [
                'user_id' => $user->id,
                'merchant_order_id' => $request['merchant_order_id'],
                'deposit_method' => RechargeRequest::METHOD_GATEWAY,
                'status' => 'pending',
            ]);

            $this->assertSame('income', $request['transaction_type']);
            $this->assertSame('recharge', $request['accounting_type']);
            $this->assertSame('merchant-channel', $request['payment']);
            $this->assertSame('450000.00', $request['amount']);
            $this->assertNotEmpty($request->header('OC-Key'));
            $this->assertNotEmpty($request->header('OC-Signature'));

            return Http::response([
                'result' => 'success',
                'data' => [
                    'payment' => [
                        'payment_status' => 'pending',
                        'payment_uid' => 'PAY-1',
                        'channel' => 'channel-a',
                        'currency' => 'vnd',
                    ],
                    'payment_url' => 'https://gateway.example/pay/PAY-1',
                ],
            ]);
        });

        $response = $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertCreated();

        $response->assertJsonPath('data.payment_url', 'https://gateway.example/pay/PAY-1')
            ->assertJsonPath('data.recharge_request.deposit_method', 'gateway')
            ->assertJsonPath('data.recharge_request.gateway_status', 'pending');
        $this->assertStringNotContainsString('test-secret', $response->getContent());
        $this->assertStringNotContainsString('test-key', $response->getContent());
        $this->assertStringNotContainsString('merchant-123', $response->getContent());

        $this->assertDatabaseHas('recharge_requests', [
            'gateway_payment_uid' => 'PAY-1',
            'gateway_payment_url' => 'https://gateway.example/pay/PAY-1',
        ]);
        $this->assertDatabaseHas('payment_gateway_events', [
            'event_type' => 'gateway.order.created',
            'processing_result' => 'created',
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_disabled_or_failed_gateway_never_credits_wallet(): void
    {
        $user = $this->user('disabled_user');
        Sanctum::actingAs($user);

        config(['services.qc168.enabled' => false]);
        $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertStatus(503);
        $this->assertDatabaseCount('recharge_requests', 0);

        config(['services.qc168.enabled' => true]);
        Http::fake([
            'https://gateway.example/*' => Http::response([
                'result' => 'error',
                'data' => ['error' => 'Channel unavailable'],
            ]),
        ]);

        $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertStatus(503);

        $this->assertDatabaseHas('recharge_requests', [
            'gateway_payment_status' => 'create_failed',
            'status' => 'rejected',
        ]);
        $rejected = RechargeRequest::query()->latest('id')->firstOrFail();
        $this->assertFalse(data_get($rejected->gateway_meta, 'order_created'));
        $this->assertSame(
            'Gateway Order Creation Failed',
            $this->rechargeTableValue('statusLabel', $rejected)
        );
        $this->assertStringContainsString(
            'did not create',
            $this->rechargeTableValue('gatewayFriendlyAlert', $rejected)
        );
        $this->assertDatabaseHas('payment_gateway_events', [
            'recharge_request_id' => $rejected->id,
            'event_type' => 'gateway.order.create_rejected',
            'processing_result' => 'create_failed',
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);

        $rejected->forceFill(['created_at' => now()->subMinutes(10)])->save();
        $requestCount = Http::recorded()->count();
        $this->artisan('qc168:sync-pending-recharges')->assertSuccessful();
        $this->assertCount($requestCount, Http::recorded());

        Http::fake(Http::failedConnection('Connection timed out'));
        $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertStatus(503);
        $this->assertSame(1, RechargeRequest::query()
            ->where('gateway_payment_status', 'create_failed')
            ->count());
        $uncertain = RechargeRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('create_pending', $uncertain->gateway_payment_status);
        $this->assertSame('pending', $uncertain->status);
        $this->assertNull(data_get($uncertain->gateway_meta, 'order_created'));
        $this->assertDatabaseHas('payment_gateway_events', [
            'recharge_request_id' => $uncertain->id,
            'event_type' => 'gateway.order.submission_uncertain',
            'processing_result' => 'submission_uncertain',
        ]);
        $this->assertSame(
            'Status Needs Confirmation',
            $this->rechargeTableValue('statusLabel', $uncertain)
        );
        $this->assertTrue($this->rechargeTableValue('hasGatewayAttention', $uncertain));
        $this->assertFalse($this->rechargeTableValue('hasGatewayWarning', $uncertain));
        $this->assertDatabaseCount('wallet_transactions', 0);

        config(['services.qc168.payment' => null]);
        $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertStatus(503);
        $this->assertDatabaseCount('recharge_requests', 2);
    }

    public function test_signed_callback_after_explicit_create_rejection_is_audited_and_processed_safely(): void
    {
        $user = $this->user('deposit_rejection_contradiction');
        Sanctum::actingAs($user);
        Http::fake(fn () => Http::response([
            'result' => 'error',
            'data' => 'Order was not created',
        ]));

        $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertStatus(503);
        $recharge = RechargeRequest::query()->firstOrFail();
        $this->assertTrue($recharge->isRejected());

        $this->sendCallback($this->callbackPayload($recharge, 'completed'))
            ->assertOk();

        $recharge->refresh();
        $this->assertTrue($recharge->isApproved());
        $this->assertSame('completed', $recharge->gateway_payment_status);
        $this->assertSame('450000.00', Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->value('balance'));
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', RechargeRequest::class)
            ->where('reference_id', $recharge->id)
            ->count());
        $event = PaymentGatewayEvent::query()
            ->where('recharge_request_id', $recharge->id)
            ->where('event_type', 'gateway.callback.create_rejected_contradiction')
            ->firstOrFail();
        $this->assertSame('high', $event->payload['audit_priority']);
    }

    public function test_success_result_remains_a_created_order_when_the_payment_url_is_invalid(): void
    {
        $user = $this->user('deposit_created_invalid_url');
        Sanctum::actingAs($user);
        Http::fake(fn () => Http::response([
            'result' => 'success',
            'data' => [
                'payment' => [
                    'payment_status' => 'pending',
                    'payment_uid' => 'PAY-NO-URL',
                    'currency' => 'vnd',
                ],
            ],
        ]));

        $this->postJson('/api/v1/recharge/gateway', ['amount' => '450000.00'])
            ->assertStatus(503);

        $recharge = RechargeRequest::query()->firstOrFail();
        $this->assertTrue($recharge->isPending());
        $this->assertSame('pending', $recharge->gateway_payment_status);
        $this->assertTrue(data_get($recharge->gateway_meta, 'order_created'));
        $this->assertDatabaseHas('payment_gateway_events', [
            'recharge_request_id' => $recharge->id,
            'event_type' => 'gateway.order.created',
        ]);
        $this->assertDatabaseMissing('payment_gateway_events', [
            'recharge_request_id' => $recharge->id,
            'event_type' => 'gateway.order.create_rejected',
        ]);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_verified_completed_callback_credits_exactly_once_across_retries(): void
    {
        [$user, $recharge] = $this->gatewayRecharge('completed_user', '1000.00');
        $payload = $this->callbackPayload($recharge, 'completed');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->sendCallback($payload)->assertOk()->assertSeeText('success');
        }

        $recharge->refresh();
        $this->assertTrue($recharge->isApproved());
        $this->assertSame('completed', $recharge->gateway_payment_status);
        $this->assertNotNull($recharge->gateway_completed_at);
        $this->assertSame('1000.00', Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->value('balance'));
        $this->assertSame('1000.00', $user->fresh()->accumulated_recharge);
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', RechargeRequest::class)
            ->where('reference_id', $recharge->id)
            ->count());
        $this->assertSame(3, PaymentGatewayEvent::query()->count());
    }

    public function test_processing_with_diagnostic_reason_stays_processing_and_shows_a_secondary_warning(): void
    {
        [$user, $recharge] = $this->gatewayRecharge('deposit_processing_warning', '1000.00');
        $reason = 'CallserverErr: Client.Timeout exceeded while awaiting headers';
        $payload = [
            ...$this->callbackPayload($recharge, 'processing'),
            'payment_fail_reason' => $reason,
        ];

        $this->sendCallback($payload)->assertOk();

        $recharge->refresh();
        $this->assertSame('pending', $recharge->status);
        $this->assertSame('processing', $recharge->gateway_payment_status);
        $this->assertSame($reason, $recharge->gateway_fail_reason);
        $this->assertSame('Processing', $this->rechargeTableValue('statusLabel', $recharge));
        $this->assertSame('info', $this->rechargeTableValue('statusColor', $recharge));
        $this->assertStringContainsString(
            'timed out',
            strtolower($this->rechargeTableValue('gatewayFriendlyAlert', $recharge))
        );
        $this->assertFalse($this->rechargeTableValue('hasGatewayAttention', $recharge));
        $this->assertTrue($this->rechargeTableValue('hasGatewayWarning', $recharge));

        Sanctum::actingAs($user);
        $this->getJson("/api/v1/recharge/{$recharge->id}/status")
            ->assertOk()
            ->assertDontSee('CallserverErr')
            ->assertDontSee('Client.Timeout');
    }

    public function test_completed_with_stale_diagnostic_is_approved_without_active_attention(): void
    {
        [$user, $recharge] = $this->gatewayRecharge('deposit_complete_stale', '1000.00');
        $reason = 'Client.Timeout exceeded while awaiting headers';
        $processing = [
            ...$this->callbackPayload($recharge, 'processing'),
            'payment_fail_reason' => $reason,
        ];
        $completed = [
            ...$this->callbackPayload($recharge, 'completed'),
            'payment_fail_reason' => $reason,
        ];

        $this->sendCallback($processing)->assertOk();
        $this->sendCallback($completed)->assertOk();
        $this->sendCallback($completed)->assertOk();

        $recharge->refresh();
        $this->assertTrue($recharge->isApproved());
        $this->assertSame('completed', $recharge->gateway_payment_status);
        $this->assertSame($reason, $recharge->gateway_fail_reason);
        $this->assertSame('Approved', $this->rechargeTableValue('statusLabel', $recharge));
        $this->assertSame('success', $this->rechargeTableValue('statusColor', $recharge));
        $this->assertNull($this->rechargeTableValue('gatewayFriendlyAlert', $recharge));
        $this->assertFalse($this->rechargeTableValue('hasGatewayAttention', $recharge));
        $this->assertFalse($this->rechargeTableValue('hasGatewayWarning', $recharge));
        $this->assertSame('1000.00', Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->value('balance'));
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', RechargeRequest::class)
            ->where('reference_id', $recharge->id)
            ->count());
    }

    public function test_order_sync_changes_unknown_to_processing_then_completed_using_root_data_payload(): void
    {
        [$user, $recharge] = $this->gatewayRecharge('deposit_sync_unknown', '1000.00');
        $reason = 'Client.Timeout exceeded while awaiting headers';
        $recharge->forceFill([
            'gateway_payment_status' => 'create_pending',
            'gateway_fail_reason' => $reason,
        ])->save();

        $this->assertSame(
            'Status Needs Confirmation',
            $this->rechargeTableValue('statusLabel', $recharge)
        );

        Http::fakeSequence()
            ->push([
                'result' => 'success',
                'data' => [
                    ...$this->callbackPayload($recharge, 'processing'),
                    'payment_fail_reason' => '',
                ],
            ])
            ->push([
                'result' => 'success',
                'data' => [
                    ...$this->callbackPayload($recharge, 'completed'),
                    'payment_fail_reason' => '',
                ],
            ])
            ->push([
                'result' => 'success',
                'data' => [
                    ...$this->callbackPayload($recharge, 'completed'),
                    'payment_fail_reason' => '',
                ],
            ]);
        $processingResult = app(Qc168CallbackService::class)->sync($recharge);
        $this->assertTrue($processingResult['accepted'], json_encode($processingResult));

        $recharge->refresh();
        $this->assertSame('processing', $recharge->gateway_payment_status);
        $this->assertSame($reason, $recharge->gateway_fail_reason);
        $this->assertSame('Processing', $this->rechargeTableValue('statusLabel', $recharge));
        $this->assertTrue($this->rechargeTableValue('hasGatewayWarning', $recharge));

        $completedResult = app(Qc168CallbackService::class)->sync($recharge);
        $this->assertTrue($completedResult['accepted'], json_encode($completedResult));
        $this->assertSame('auto_approved', $completedResult['result']);
        app(Qc168CallbackService::class)->sync($recharge->fresh());

        $recharge->refresh();
        $this->assertTrue($recharge->isApproved());
        $this->assertSame('completed', $recharge->gateway_payment_status);
        $this->assertSame($reason, $recharge->gateway_fail_reason);
        $this->assertSame(1, WalletTransaction::query()
            ->where('reference_type', RechargeRequest::class)
            ->where('reference_id', $recharge->id)
            ->count());
        $this->assertSame('1000.00', Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->value('balance'));
    }

    public function test_completed_deposit_invalidates_balance_after_commit_but_duplicate_does_not(): void
    {
        [, $recharge] = $this->gatewayRecharge('balance_invalidation', '1000.00');
        $balanceService = \Mockery::mock(Qc168BalanceService::class);
        $balanceService->shouldReceive('markStale')
            ->once()
            ->andReturnUsing(function () use ($recharge): void {
                $this->assertSame(0, DB::transactionLevel());
                $this->assertTrue($recharge->fresh()->isApproved());
            });
        $this->app->instance(Qc168BalanceService::class, $balanceService);
        $callbackService = app(Qc168CallbackService::class);

        $completed = $callbackService->processProviderState(
            $recharge,
            $this->callbackPayload($recharge, 'completed'),
            'test',
        );
        $duplicate = $callbackService->processProviderState(
            $recharge->fresh(),
            $this->callbackPayload($recharge->fresh(), 'completed'),
            'test',
        );

        $this->assertSame('auto_approved', $completed['result']);
        $this->assertContains($duplicate['result'], ['duplicate_completed', 'stale_status_ignored']);
    }

    public function test_callback_validation_blocks_tampering_and_order_mismatches(): void
    {
        $mutations = [
            'amount_mismatch' => ['amount' => '2000.00'],
            'merchant_mismatch' => ['merchant_uid' => 'another-merchant'],
            'currency_mismatch' => ['currency' => 'usd'],
            'transaction_type_mismatch' => ['transaction_type' => 'expense'],
        ];

        foreach ($mutations as $username => $changes) {
            [, $recharge] = $this->gatewayRecharge($username, '1000.00');
            $payload = [...$this->callbackPayload($recharge, 'completed'), ...$changes];

            $this->sendCallback($payload)->assertStatus(422);
            $this->assertSame('pending', $recharge->fresh()->status);
            $this->assertDatabaseMissing('wallet_transactions', [
                'reference_type' => RechargeRequest::class,
                'reference_id' => $recharge->id,
            ]);
        }

        [, $recharge] = $this->gatewayRecharge('bad_signature', '1000.00');
        $this->postJson('/payment-gateway/qc168/callback', $this->callbackPayload($recharge, 'completed'), [
            'Oc-Signature' => str_repeat('0', 64),
        ])->assertStatus(401);
        $this->assertSame('pending', $recharge->fresh()->status);
    }

    #[DataProvider('inviteDayApprovalDates')]
    public function test_gateway_completion_runs_first_deposit_invite_hook_once(string $registeredAt, string $approvedAt, string $expectedPoints): void
    {
        $this->travelTo(Carbon::parse($approvedAt));
        Setting::setValue(PointService::SETTING_POINTS_ENABLED, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_ENABLED, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_AMOUNT, 25);
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);

        $referrer = $this->user('invite_referrer');
        $user = $this->user('invite_depositor');
        $user->forceFill([
            'referrer_id' => $referrer->id,
            'created_at' => $registeredAt,
        ])->saveQuietly();
        $recharge = RechargeRequest::query()->create([
            'user_id' => $user->id,
            'amount' => '1000.00',
            'deposit_method' => RechargeRequest::METHOD_GATEWAY,
            'gateway_provider' => RechargeRequest::GATEWAY_QC168,
            'merchant_order_id' => 'DEPFIRSTDEPOSIT',
            'gateway_payment_status' => 'pending',
            'gateway_currency' => 'vnd',
            'status' => 'pending',
        ]);
        $payload = $this->callbackPayload($recharge, 'completed');

        $this->sendCallback($payload)->assertOk();
        $this->sendCallback($payload)->assertOk();

        $this->assertSame('approved', $recharge->fresh()->status);
        $this->assertSame('1000.00', Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_RECHARGE)->value('balance'));
        $this->assertSame($expectedPoints, $referrer->fresh()->points_balance);
        $this->assertSame($expectedPoints === '0.00' ? 0 : 1, PointTransaction::query()
            ->where('type', PointTransaction::TYPE_INVITE_DAY_REGISTER)
            ->where('reference_type', User::class)
            ->where('reference_id', $user->id)
            ->count());
    }

    public static function inviteDayApprovalDates(): array
    {
        return [
            'same Thursday' => ['2026-09-10 09:00:00', '2026-09-10 10:00:00', '25.00'],
            'same Friday before midnight' => ['2026-09-11 09:00:00', '2026-09-11 23:59:59', '25.00'],
            'Thursday registration Monday approval' => ['2026-09-10 18:20:12', '2026-09-14 14:59:00', '0.00'],
            'Friday registration Monday approval' => ['2026-09-11 22:19:54', '2026-09-14 20:48:43', '0.00'],
            'Thursday registration Friday approval' => ['2026-09-10 09:00:00', '2026-09-11 10:00:00', '0.00'],
            'crossing midnight between invite days' => ['2026-09-10 23:59:59', '2026-09-11 00:00:00', '0.00'],
            'next Thursday' => ['2026-09-10 09:00:00', '2026-09-17 10:00:00', '0.00'],
            'same non-invite day' => ['2026-09-14 09:00:00', '2026-09-14 10:00:00', '0.00'],
            'Monday registration Thursday approval' => ['2026-09-14 09:00:00', '2026-09-17 10:00:00', '0.00'],
        ];
    }

    public function test_non_completed_and_refund_callbacks_never_credit_or_debit_wallet(): void
    {
        foreach (['pending', 'preparing', 'processing', 'failed', 'reject', 'overdue', 'refund'] as $status) {
            [$user, $recharge] = $this->gatewayRecharge('status_'.$status, '1000.00');
            Wallet::query()->create([
                'user_id' => $user->id,
                'type' => Wallet::TYPE_RECHARGE,
                'balance' => 250,
                'locked_balance' => 0,
            ]);

            $payload = [
                ...$this->callbackPayload($recharge, $status),
                'payment_fail_reason' => "diagnostic {$status}",
            ];
            $this->sendCallback($payload)->assertOk();

            $recharge->refresh();
            $this->assertSame('pending', $recharge->status);
            $this->assertSame($status, $recharge->gateway_payment_status);
            $this->assertSame("diagnostic {$status}", $recharge->gateway_fail_reason);
            $this->assertSame('250.00', Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_RECHARGE)
                ->value('balance'));
            $this->assertDatabaseCount('wallet_transactions', 0);
        }
    }

    public function test_browser_return_cannot_approve_and_ownership_is_enforced(): void
    {
        [$owner, $recharge] = $this->gatewayRecharge('return_owner', '1000.00');
        $other = $this->user('return_other');

        $this->actingAs($owner)
            ->get(route('recharge.gateway.return', $recharge).'?status=completed&paid=true')
            ->assertOk();
        $this->assertSame('pending', $recharge->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);

        $this->actingAs($other)
            ->get(route('recharge.gateway.return', $recharge))
            ->assertForbidden();
        $this->getJson(route('recharge.gateway.status', $recharge))
            ->assertForbidden();
    }

    public function test_gateway_api_requires_authentication_and_enforces_status_ownership(): void
    {
        [, $recharge] = $this->gatewayRecharge('api_owner', '1000.00');

        $this->postJson('/api/v1/recharge/gateway', ['amount' => '1000.00'])
            ->assertUnauthorized();

        Sanctum::actingAs($this->user('api_other'));
        $this->getJson("/api/v1/recharge/{$recharge->id}/status")
            ->assertForbidden();
    }

    public function test_existing_manual_deposit_creation_and_approval_remain_unchanged(): void
    {
        Storage::fake('do_spaces');
        $user = $this->user('manual_user');
        $method = RechargeMethod::query()->create([
            'name' => 'Manual Bank',
            'bank_name' => 'Example Bank',
            'account_name' => 'Cafe Bustelo',
            'account_no' => '123456',
            'status' => RechargeMethod::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)->post('/recharge', [
            'amount' => '450000.00',
            'recharge_method_id' => $method->id,
            'receipt' => UploadedFile::fake()->image('receipt.png'),
        ])->assertRedirect();

        $recharge = RechargeRequest::query()->firstOrFail();
        $this->assertTrue($recharge->isManualDeposit());
        $this->assertTrue($recharge->isPending());
        $this->assertNotNull($recharge->receipt_path);

        app(RechargeApprovalService::class)->approve($recharge, null);

        $this->assertTrue($recharge->fresh()->isApproved());
        $this->assertSame('450000.00', Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->value('balance'));
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'name' => str_replace('_', ' ', $username),
            'username' => $username,
            'password' => Hash::make('password'),
        ])->refresh();
    }

    private function gatewayRecharge(string $username, string $amount): array
    {
        $user = $this->user($username);
        $recharge = RechargeRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'deposit_method' => RechargeRequest::METHOD_GATEWAY,
            'gateway_provider' => RechargeRequest::GATEWAY_QC168,
            'merchant_order_id' => 'DEP'.strtoupper($username),
            'gateway_payment_status' => 'pending',
            'gateway_currency' => 'vnd',
            'status' => 'pending',
        ]);

        return [$user, $recharge];
    }

    private function callbackPayload(RechargeRequest $recharge, string $status): array
    {
        return [
            'transaction_type' => 'income',
            'merchant_uid' => 'merchant-123',
            'amount' => $recharge->amount,
            'final_amount' => $recharge->amount,
            'currency' => 'VND',
            'notify_url' => route('payment-gateway.qc168.callback'),
            'merchant_order_id' => $recharge->merchant_order_id,
            'payment_status' => $status,
            'payment_fail_reason' => '',
            'order_uid' => 'ORDER-'.$recharge->id,
            'signture_key' => 'callback-key',
            'tenant' => 'tenant-1',
        ];
    }

    private function sendCallback(array $payload)
    {
        $signature = app(Qc168Gateway::class)->sign($payload);

        return $this->postJson('/payment-gateway/qc168/callback', $payload, [
            'Oc-Signature' => $signature,
        ]);
    }

    private function rechargeTableValue(
        string $method,
        RechargeRequest $recharge,
    ): mixed {
        $reflection = new \ReflectionMethod(RechargeRequestsTable::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $recharge);
    }
}
