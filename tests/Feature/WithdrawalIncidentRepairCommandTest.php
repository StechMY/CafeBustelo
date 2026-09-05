<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\PaymentGatewayEvent;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawalPayoutAttempt;
use App\Models\WithdrawRequest;
use App\Policies\WithdrawRequestPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use LogicException;
use PDO;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WithdrawalIncidentRepairCommandTest extends TestCase
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
            'services.qc168.merchant_uid' => 'merchant-123',
        ]);

        $this->artisan('migrate:fresh')->run();
    }

    public function test_incident_repair_dry_run_reports_without_writing_or_contacting_qc168(): void
    {
        $this->artisan('qc168:repair-incident-903-1079')
            ->expectsOutputToContain('Choose exactly one mode')
            ->assertFailed();
        $this->artisan('qc168:repair-incident-903-1079', ['--dry-run' => true, '--apply' => true])
            ->expectsOutputToContain('Choose exactly one mode')
            ->assertFailed();

        $this->seedMainIncident();
        Http::fake();
        $eventCount = PaymentGatewayEvent::query()->count();

        $this->artisan('qc168:repair-incident-903-1079', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Before')
            ->expectsOutputToContain('No changes were made')
            ->assertSuccessful();

        $this->assertWallet(18455, 240000);
        $this->assertSame(WithdrawRequest::PAYOUT_PROCESSING, WithdrawRequest::findOrFail(1079)->payout_status);
        $this->assertSame($eventCount, PaymentGatewayEvent::query()->count());
        Http::assertNothingSent();
    }

    public function test_incident_repair_applies_once_and_preserves_available_and_1113_reserve(): void
    {
        $this->seedMainIncident();
        Http::fake();

        $this->artisan('qc168:repair-incident-903-1079', ['--apply' => true])
            ->expectsOutputToContain('APPLY')
            ->expectsOutputToContain('Before')
            ->expectsOutputToContain('After')
            ->assertSuccessful();

        $this->assertWallet(18455, 120000);
        $withdrawal1079 = WithdrawRequest::findOrFail(1079);
        $this->assertSame(WithdrawRequest::PAYOUT_COMPLETED, $withdrawal1079->payout_status);
        $this->assertSame(WithdrawalPayoutAttempt::STATUS_COMPLETED, $withdrawal1079->gateway_payment_status);
        $this->assertSame(WithdrawRequest::PAYOUT_WAITING_RELEASE, WithdrawRequest::findOrFail(1113)->payout_status);
        $this->assertSame(1, WithdrawalPayoutAttempt::query()->where('withdraw_request_id', 1079)->count());
        $this->assertSame(2, WalletTransaction::query()->count());
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => 903,
            'event_type' => 'withdrawal.financial_incident_reconciled',
            'source' => 'artisan_repair',
            'processing_result' => 'locked_reserve_reconciled',
        ]);
        $this->assertDatabaseHas('payment_gateway_events', [
            'withdraw_request_id' => 1079,
            'event_type' => 'withdrawal.gateway_completed',
        ]);
        Http::assertNothingSent();

        $eventCount = PaymentGatewayEvent::query()->count();
        $this->artisan('qc168:repair-incident-903-1079', ['--apply' => true])
            ->assertFailed();
        $this->assertWallet(18455, 120000);
        $this->assertSame($eventCount, PaymentGatewayEvent::query()->count());
    }

    public function test_incident_repair_precondition_mismatch_aborts_without_any_write(): void
    {
        $this->seedMainIncident();
        WithdrawRequest::findOrFail(1113)->forceFill(['payout_status' => WithdrawRequest::PAYOUT_PENDING])->save();
        $eventCount = PaymentGatewayEvent::query()->count();

        $this->artisan('qc168:repair-incident-903-1079', ['--apply' => true])
            ->expectsOutputToContain('Precondition failed')
            ->assertFailed();

        $this->assertWallet(18455, 240000);
        $this->assertSame(WithdrawRequest::PAYOUT_PROCESSING, WithdrawRequest::findOrFail(1079)->payout_status);
        $this->assertSame($eventCount, PaymentGatewayEvent::query()->count());
    }

    public function test_incident_repair_rolls_back_reserve_restore_when_confirmed_payload_is_invalid(): void
    {
        $this->seedMainIncident();
        $event = PaymentGatewayEvent::query()
            ->where('withdraw_request_id', 1079)
            ->where('payment_status', 'completed')
            ->firstOrFail();
        DB::table('payment_gateway_events')->where('id', $event->id)->update([
            'payload' => json_encode([...$event->payload, 'amount' => '1.00'], JSON_THROW_ON_ERROR),
        ]);
        $eventCount = PaymentGatewayEvent::query()->count();

        $this->artisan('qc168:repair-incident-903-1079', ['--apply' => true])
            ->assertFailed();

        $this->assertWallet(18455, 240000);
        $this->assertSame(WithdrawRequest::PAYOUT_PROCESSING, WithdrawRequest::findOrFail(1079)->payout_status);
        $this->assertSame($eventCount, PaymentGatewayEvent::query()->count());
    }

    public function test_status_only_repair_changes_946_and_947_without_wallet_changes(): void
    {
        $this->artisan('withdrawals:repair-cancelled-status-946-947')
            ->expectsOutputToContain('Choose exactly one mode')
            ->assertFailed();
        $this->artisan('withdrawals:repair-cancelled-status-946-947', ['--dry-run' => true, '--apply' => true])
            ->expectsOutputToContain('Choose exactly one mode')
            ->assertFailed();

        $this->seedStatusIncidents();
        $before = Wallet::query()->orderBy('id')->get(['id', 'balance', 'locked_balance'])->toArray();

        $this->artisan('withdrawals:repair-cancelled-status-946-947', ['--apply' => true])
            ->expectsOutputToContain('After')
            ->assertSuccessful();

        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, WithdrawRequest::findOrFail(946)->payout_status);
        $this->assertSame(WithdrawRequest::PAYOUT_CANCELLED, WithdrawRequest::findOrFail(947)->payout_status);
        $this->assertSame($before, Wallet::query()->orderBy('id')->get(['id', 'balance', 'locked_balance'])->toArray());
        $this->assertSame(2, PaymentGatewayEvent::query()
            ->where('event_type', 'withdrawal.cancelled_status_reconciled')
            ->count());

        $this->artisan('withdrawals:repair-cancelled-status-946-947', ['--apply' => true])
            ->assertFailed();
    }

    public function test_status_only_repair_validates_both_rows_before_changing_either(): void
    {
        $this->seedStatusIncidents();
        WalletTransaction::query()->where('reference_id', 947)->update(['amount' => '1.00']);

        $this->artisan('withdrawals:repair-cancelled-status-946-947', ['--apply' => true])
            ->expectsOutputToContain('Precondition failed')
            ->assertFailed();

        $this->assertSame(WithdrawRequest::PAYOUT_REJECTED, WithdrawRequest::findOrFail(946)->payout_status);
        $this->assertSame(WithdrawRequest::PAYOUT_REJECTED, WithdrawRequest::findOrFail(947)->payout_status);
        $this->assertDatabaseMissing('payment_gateway_events', [
            'event_type' => 'withdrawal.cancelled_status_reconciled',
        ]);
    }

    public function test_withdraw_requests_cannot_be_deleted_even_with_delete_permissions(): void
    {
        $this->seedMainIncident();
        $admin = AdminUser::query()->create([
            'username' => 'delete-admin',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        foreach (['Delete:WithdrawRequest', 'DeleteAny:WithdrawRequest', 'ForceDelete:WithdrawRequest', 'ForceDeleteAny:WithdrawRequest'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $withdrawal = WithdrawRequest::findOrFail(1113);
        $policy = new WithdrawRequestPolicy;

        $this->assertFalse($policy->delete($admin, $withdrawal));
        $this->assertFalse($policy->deleteAny($admin));
        $this->assertFalse($policy->forceDelete($admin, $withdrawal));
        $this->assertFalse($policy->forceDeleteAny($admin));

        try {
            $withdrawal->delete();
            $this->fail('Withdraw requests must be immutable against deletion.');
        } catch (LogicException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseHas('withdraw_requests', ['id' => 1113]);
    }

    private function seedMainIncident(): void
    {
        $user = $this->createUser(555, 'incident-user');
        $wallet = Wallet::query()->forceCreate([
            'id' => 1110,
            'user_id' => $user->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
            'balance' => '18455.00',
            'locked_balance' => '240000.00',
        ]);

        $withdrawal903 = $this->createWithdrawal(903, $user, '180000.00', '176400.00', WithdrawRequest::PAYOUT_CANCELLED);
        $withdrawal1079 = $this->createWithdrawal(1079, $user, '300000.00', '279000.00', WithdrawRequest::PAYOUT_PROCESSING, [
            'gateway_current_merchant_order_id' => 'WD1079-A1-01M1P8PJRQX3R814QM7QRX4Y87',
            'gateway_payment_status' => WithdrawalPayoutAttempt::STATUS_PROCESSING,
        ]);
        $this->createWithdrawal(1113, $user, '120000.00', '117600.00', WithdrawRequest::PAYOUT_WAITING_RELEASE);

        foreach ([1, 2] as $index) {
            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => Wallet::TYPE_WITHDRAWABLE,
                'type' => 'withdraw_gateway_cancelled',
                'direction' => WalletTransaction::DIRECTION_CREDIT,
                'amount' => '180000.00',
                'balance_before' => $index === 1 ? '0.00' : '180000.00',
                'balance_after' => $index === 1 ? '180000.00' : '360000.00',
                'reference_type' => WithdrawRequest::class,
                'reference_id' => $withdrawal903->id,
            ]);
        }

        $attempt = WithdrawalPayoutAttempt::query()->forceCreate([
            'id' => 509,
            'withdraw_request_id' => $withdrawal1079->id,
            'attempt_no' => 1,
            'provider' => WithdrawRequest::GATEWAY_QC168,
            'merchant_order_id' => 'WD1079-A1-01M1P8PJRQX3R814QM7QRX4Y87',
            'amount' => '279000.00',
            'currency' => 'vnd',
            'payment_channel' => 'vn02',
            'bank_name_snapshot' => 'Vietcombank',
            'bank_code_snapshot' => '970436',
            'bank_account_name_snapshot' => 'Incident User',
            'bank_account_no_snapshot' => '1234567890',
            'gateway_status' => WithdrawalPayoutAttempt::STATUS_PROCESSING,
        ]);

        PaymentGatewayEvent::query()->create([
            'provider' => WithdrawRequest::GATEWAY_QC168,
            'merchant_order_id' => $attempt->merchant_order_id,
            'withdraw_request_id' => $withdrawal1079->id,
            'withdrawal_payout_attempt_id' => $attempt->id,
            'source' => 'qc168_callback',
            'event_type' => 'gateway.withdrawal.callback.processing_error',
            'payment_status' => WithdrawalPayoutAttempt::STATUS_COMPLETED,
            'signature_valid' => true,
            'payload' => [
                'transaction_type' => 'expense',
                'merchant_uid' => 'merchant-123',
                'merchant_order_id' => $attempt->merchant_order_id,
                'amount' => '279000.00',
                'final_amount' => '279000.00',
                'currency' => 'vnd',
                'payment' => 'vn02',
                'payment_status' => 'completed',
                'order_uid' => 'ORDER-1079',
                'payment_uid' => 'PAY-1079',
            ],
            'received_at' => now(),
            'processed_at' => now(),
            'processing_result' => 'processing_error',
        ]);
    }

    private function seedStatusIncidents(): void
    {
        foreach ([946 => [723, 200000], 947 => [700, 223000]] as $withdrawalId => [$userId, $amount]) {
            $user = $this->createUser($userId, 'status-user-'.$userId);
            $wallet = Wallet::query()->forceCreate([
                'id' => 2000 + $userId,
                'user_id' => $user->id,
                'type' => Wallet::TYPE_WITHDRAWABLE,
                'balance' => '999.00',
                'locked_balance' => '0.00',
            ]);
            $withdrawal = $this->createWithdrawal(
                $withdrawalId,
                $user,
                number_format($amount, 2, '.', ''),
                number_format($amount, 2, '.', ''),
                WithdrawRequest::PAYOUT_REJECTED,
            );
            WalletTransaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_type' => Wallet::TYPE_WITHDRAWABLE,
                'type' => 'withdraw_gateway_cancelled',
                'direction' => WalletTransaction::DIRECTION_CREDIT,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_before' => '0.00',
                'balance_after' => number_format($amount, 2, '.', ''),
                'reference_type' => WithdrawRequest::class,
                'reference_id' => $withdrawal->id,
            ]);
        }
    }

    private function createUser(int $id, string $username): User
    {
        return User::query()->forceCreate([
            'id' => $id,
            'name' => $username,
            'username' => $username,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function createWithdrawal(
        int $id,
        User $user,
        string $amount,
        string $netAmount,
        string $payoutStatus,
        array $attributes = [],
    ): WithdrawRequest {
        return WithdrawRequest::query()->forceCreate([
            'id' => $id,
            'user_id' => $user->id,
            'amount' => $amount,
            'fee_amount' => number_format((float) $amount - (float) $netAmount, 2, '.', ''),
            'net_amount' => $netAmount,
            'vip_level_snapshot' => 1,
            'min_withdraw_amount_snapshot' => '1.00',
            'withdraw_fee_percent_snapshot' => '2.00',
            'bank_name' => 'Vietcombank',
            'bank_code' => '970436',
            'bank_account_name' => 'Incident User',
            'bank_account_no' => '1234567890',
            'status' => WithdrawRequest::STATUS_APPROVED,
            'approval_mode' => WithdrawRequest::APPROVAL_MODE_GATEWAY_SCHEDULED,
            'gateway_provider' => WithdrawRequest::GATEWAY_QC168,
            'payout_status' => $payoutStatus,
            ...$attributes,
        ]);
    }

    private function assertWallet(float $balance, float $lockedBalance): void
    {
        $wallet = Wallet::findOrFail(1110);

        $this->assertSame(number_format($balance, 2, '.', ''), $wallet->balance);
        $this->assertSame(number_format($lockedBalance, 2, '.', ''), $wallet->locked_balance);
    }
}
