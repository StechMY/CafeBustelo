<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBankAccount;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use App\Rules\UniqueBankAccountName;
use App\Services\WalletService;
use App\Services\WithdrawService;
use App\Support\BankAccountNameMatcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PDO;
use RuntimeException;
use Tests\TestCase;

class BankAccountNameSecurityTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        $connection = 'sqlite';
        $database = ':memory:';

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $database = (string) getenv('BANK_ACCOUNT_SECURITY_TEST_DATABASE');

            if ($database === '') {
                $this->markTestSkipped(
                    'SQLite PDO is unavailable and BANK_ACCOUNT_SECURITY_TEST_DATABASE is not set.',
                );
            }

            $connection = 'mysql';
        }

        putenv("DB_CONNECTION={$connection}");
        putenv("DB_DATABASE={$database}");
        $_ENV['DB_CONNECTION'] = $connection;
        $_ENV['DB_DATABASE'] = $database;
        $_SERVER['DB_CONNECTION'] = $connection;
        $_SERVER['DB_DATABASE'] = $database;

        parent::setUp();

        config([
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
        ]);
    }

    public function test_future_similar_name_fails_validation_and_database_uniqueness(): void
    {
        $this->createBankAccount($this->createUser(), 'Nguyenhoasim');

        $validator = Validator::make([
            'bank_account_name' => 'Nguyễn hoa sim.',
        ], [
            'bank_account_name' => [new UniqueBankAccountName],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('bank_account.name_already_bound'),
            $validator->errors()->first('bank_account_name'),
        );

        $this->expectException(QueryException::class);
        $this->createBankAccount($this->createUser(), 'Nguyen hoa sim.');
    }

    public function test_existing_similar_names_on_different_users_block_withdraw_submission(): void
    {
        [$blockedAccount, $blockedUser, $otherAccount] = $this->createLegacySimilarAccounts();

        $this->assertTrue($blockedAccount->hasSimilarNameOnAnotherUser());
        $this->assertTrue($otherAccount->hasSimilarNameOnAnotherUser());

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldNotReceive('lock');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('bank_account.similar_name_withdraw_blocked'));

        (new WithdrawService($walletService))->submit($blockedUser, 100);
    }

    public function test_existing_similar_name_blocks_pending_withdraw_approval(): void
    {
        [$bankAccount, $blockedUser] = $this->createLegacySimilarAccounts();

        $withdrawRequest = WithdrawRequest::query()->create([
            'user_id' => $blockedUser->id,
            'user_bank_account_id' => $bankAccount->id,
            'amount' => 100,
            'fee_amount' => 0,
            'net_amount' => 100,
            'vip_level_snapshot' => 1,
            'min_withdraw_amount_snapshot' => 0,
            'withdraw_fee_percent_snapshot' => 0,
            'bank_name' => $bankAccount->bank_name,
            'bank_account_name' => $bankAccount->bank_account_name,
            'bank_account_no' => $bankAccount->bank_account_no,
            'status' => 'pending',
        ]);

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldNotReceive('consumeLocked');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('bank_account.similar_name_withdraw_blocked'));

        (new WithdrawService($walletService))->approve($withdrawRequest, null);
    }

    public function test_unique_bank_account_name_can_still_submit_withdraw(): void
    {
        $user = $this->createUser();
        $this->createBankAccount($user, 'Distinct Account Holder');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('lock')
            ->once()
            ->andReturn(new WalletTransaction);

        $withdrawRequest = (new WithdrawService($walletService))->submit($user, 100);

        $this->assertSame('pending', $withdrawRequest->status);
        $this->assertSame('100.00', $withdrawRequest->amount);
    }

    private function createLegacySimilarAccounts(): array
    {
        $firstUser = $this->createUser();
        $secondUser = $this->createUser();
        $firstAccount = $this->createBankAccount($firstUser, 'Nguyenhoasim');
        $matchHash = BankAccountNameMatcher::matchHash('Nguyen hoa sim.');
        $now = now();

        $secondAccountId = DB::table('user_bank_accounts')->insertGetId([
            'user_id' => $secondUser->id,
            'bank_account_name' => 'Nguyen hoa sim.',
            'bank_account_name_match_hash' => $matchHash,
            'bank_account_name_unique_hash' => BankAccountNameMatcher::legacyUniqueHash(999, $matchHash),
            'bank_name' => 'Test Bank',
            'bank_account_no' => 'LEGACY-'.$this->sequence,
            'status' => UserBankAccount::STATUS_ACTIVE,
            'is_locked' => true,
            'bound_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            UserBankAccount::query()->findOrFail($secondAccountId),
            $secondUser,
            $firstAccount,
        ];
    }

    private function createUser(): User
    {
        $this->sequence++;

        return User::query()->create([
            'name' => 'Bank Security User '.$this->sequence,
            'username' => 'bank-security-user-'.$this->sequence,
            'password' => Hash::make('password'),
            'status' => 'active',
            'withdraw_status' => true,
        ]);
    }

    private function createBankAccount(User $user, string $name): UserBankAccount
    {
        $this->sequence++;

        return UserBankAccount::query()->create([
            'user_id' => $user->id,
            'bank_account_name' => $name,
            'bank_name' => 'Test Bank',
            'bank_account_no' => 'BANK-'.$this->sequence,
            'status' => UserBankAccount::STATUS_ACTIVE,
            'is_locked' => true,
            'bound_at' => now(),
        ]);
    }
}
