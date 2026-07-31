<?php

namespace Tests\Feature\Api\Internal;

use App\Models\BonusClaim;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\DashboardApiClient;
use App\Models\Product;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class ManagementDashboardTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/internal/v1/management-dashboard';

    private int $userSequence = 0;

    protected function setUp(): void
    {
        $connection = 'sqlite';
        $database = ':memory:';

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $database = (string) getenv('MANAGEMENT_DASHBOARD_TEST_DATABASE');

            if ($database === '') {
                $this->markTestSkipped(
                    'SQLite PDO is unavailable and MANAGEMENT_DASHBOARD_TEST_DATABASE is not set.',
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
            'app.currency' => 'RM',
            'app.timezone' => 'Asia/Kuala_Lumpur',
            'dashboard.currency_code' => 'MYR',
            'dashboard.platform.code' => 'cafebustelo',
            'dashboard.platform.name' => 'CafeBustelo',
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-07-30 18:00:00', 'Asia/Kuala_Lumpur'));
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-07-30 18:00:00', 'Asia/Kuala_Lumpur'),
        );

    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authentication_is_restricted_to_active_dashboard_service_clients(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();

        $ordinaryUser = $this->createUser();
        $ordinaryToken = $ordinaryUser
            ->createToken('ordinary-user', ['dashboard:read'])
            ->plainTextToken;

        $this->withToken($ordinaryToken)
            ->getJson(self::ENDPOINT)
            ->assertForbidden();
        $this->app['auth']->forgetGuards();

        [$clientWithoutAbility, $tokenWithoutAbility] = $this->createClientToken([]);
        $this->withToken($tokenWithoutAbility)
            ->getJson(self::ENDPOINT)
            ->assertForbidden();
        $this->assertNull($clientWithoutAbility->fresh()->last_used_at);
        $this->app['auth']->forgetGuards();

        [, $inactiveToken] = $this->createClientToken(['dashboard:read'], false);
        $this->withToken($inactiveToken)
            ->getJson(self::ENDPOINT)
            ->assertForbidden();
        $this->app['auth']->forgetGuards();

        [$client, $validToken] = $this->createClientToken();
        $response = $this->withToken($validToken)
            ->getJson(self::ENDPOINT)
            ->assertOk();

        $this->assertNotNull($client->fresh()->last_used_at);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_filters_are_validated_and_omitted_filters_use_safe_defaults(): void
    {
        [, $token] = $this->createClientToken();

        $this->withToken($token)
            ->getJson(self::ENDPOINT.'?date_from=30-07-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_from');

        $this->withToken($token)
            ->getJson(self::ENDPOINT.'?date_from=2026-07-30&date_to=2026-07-29')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');

        $this->withToken($token)
            ->getJson(self::ENDPOINT.'?virtual_mode=not-supported')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('virtual_mode');

        $this->withToken($token)
            ->getJson(self::ENDPOINT.'?date_from=2025-01-01&date_to=2026-01-02')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date_to');

        $this->withToken($token)
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('filters.date_from', '2026-07-30')
            ->assertJsonPath('filters.date_to', '2026-07-30')
            ->assertJsonPath('filters.virtual_mode', 'exclude_virtual');
    }

    public function test_virtual_modes_filter_users_and_related_financial_metrics(): void
    {
        [, $token] = $this->createClientToken();
        $product = $this->createProduct('T2');
        $realUser = $this->createUser(isVirtual: false);
        $virtualUser = $this->createUser(isVirtual: true);

        $this->createWallets($realUser, 10, 5);
        $this->createWallets($virtualUser, 30, 15);
        $this->createRecharge($realUser, 'approved', 100, '2026-07-30 10:00:00');
        $this->createRecharge($virtualUser, 'approved', 200, '2026-07-30 11:00:00');
        $this->createWithdraw($realUser, 'approved', 20, '2026-07-30 12:00:00');
        $this->createWithdraw($virtualUser, 'approved', 40, '2026-07-30 13:00:00');
        $this->createUserProduct($realUser, $product, 50, 'active', '2026-07-30 09:00:00');
        $this->createUserProduct($virtualUser, $product, 70, 'active', '2026-07-30 09:30:00');

        $expectations = [
            'exclude_virtual' => [1, 100.0, 20.0, 1, 10.0, 5.0],
            'only_virtual' => [1, 200.0, 40.0, 1, 30.0, 15.0],
            'include_all' => [2, 300.0, 60.0, 2, 40.0, 20.0],
        ];

        foreach ($expectations as $mode => $expected) {
            $payload = $this->withToken($token)
                ->getJson($this->url('2026-07-30', '2026-07-30', $mode))
                ->assertOk()
                ->json();

            $this->assertSame($expected[0], $payload['common']['total_users']);
            $this->assertSame($expected[1], $payload['common']['cash_in']);
            $this->assertSame($expected[2], $payload['common']['cash_out']);
            $this->assertSame($expected[3], $payload['common']['order_count']);
            $this->assertSame(
                $expected[4],
                $payload['sections']['user_overview']['recharge_wallet_balance'],
            );
            $this->assertSame(
                $expected[5],
                $payload['sections']['user_overview']['withdrawable_wallet_balance'],
            );
            $this->assertSame(1, $payload['sections']['user_overview']['virtual_users']);
        }
    }

    public function test_date_boundaries_are_inclusive_and_values_outside_are_excluded(): void
    {
        [, $token] = $this->createClientToken();
        $before = $this->createUser(createdAt: '2026-07-29 23:59:59');
        $atStart = $this->createUser(createdAt: '2026-07-30 00:00:00');
        $atEnd = $this->createUser(createdAt: '2026-07-30 23:59:59');
        $after = $this->createUser(createdAt: '2026-07-31 00:00:00');

        $this->createRecharge($before, 'approved', 50, '2026-07-29 23:59:59');
        $this->createRecharge($atStart, 'approved', 1, '2026-07-30 00:00:00');
        $this->createRecharge($atEnd, 'approved', 2, '2026-07-30 23:59:59');
        $this->createRecharge($after, 'approved', 60, '2026-07-31 00:00:00');

        $payload = $this->withToken($token)
            ->getJson($this->url('2026-07-30', '2026-07-30'))
            ->assertOk()
            ->json();

        $this->assertSame(2, $payload['common']['new_users']);
        $this->assertSame(3.0, $payload['common']['cash_in']);
        $this->assertSame(2, $payload['sections']['operation_selected']['first_recharge_users']);
    }

    public function test_response_contains_all_business_metrics_without_sensitive_fields(): void
    {
        [, $token] = $this->createClientToken();
        $productT2 = $this->createProduct('T2');
        $productT1 = $this->createProduct('T1');
        $root = $this->createUser(status: 'active', createdAt: '2026-07-28 09:00:00');
        $levelB = $this->createUser(
            status: 'active',
            referrer: $root,
            createdAt: '2026-07-29 09:00:00',
        );
        $levelC = $this->createUser(
            status: 'active',
            referrer: $levelB,
            createdAt: '2026-07-30 09:00:00',
        );
        $this->createUser(isVirtual: true, createdAt: '2026-07-29 08:00:00');

        $rootProduct = $this->createUserProduct(
            $root,
            $productT2,
            50,
            'active',
            '2026-07-28 10:00:00',
            '2026-08-27 10:00:00',
        );
        $levelBProduct = $this->createUserProduct(
            $levelB,
            $productT2,
            60,
            'expired',
            '2026-07-29 10:00:00',
            '2026-07-30 10:00:00',
        );
        $levelCProduct = $this->createUserProduct(
            $levelC,
            $productT2,
            300,
            'active',
            '2026-07-30 10:00:00',
            '2026-08-29 10:00:00',
        );
        $this->createUserProduct(
            $levelC,
            $productT1,
            90,
            'active',
            '2026-07-30 11:00:00',
            '2026-08-29 11:00:00',
        );

        $this->createWallets($root, 25, 15);
        $this->createWallets($levelB, 5, 2);
        $this->createWallets($levelC, 10, 8);

        $this->createRecharge($root, 'approved', 5, '2026-07-27 10:00:00');
        $this->createRecharge($root, 'approved', 100, '2026-07-29 10:00:00');
        $this->createRecharge($levelB, 'approved', 10, '2026-07-29 11:00:00');
        $this->createRecharge($levelC, 'approved', 125, '2026-07-30 12:00:00');
        $this->createRecharge($levelB, 'pending', 30, null, '2026-07-29 12:00:00');
        $this->createRecharge($levelB, 'rejected', 40, '2026-07-29 13:00:00');

        $this->createWithdraw($root, 'approved', 50, '2026-07-29 14:00:00');
        $this->createWithdraw($levelB, 'approved', 6, '2026-07-30 14:00:00');
        $this->createWithdraw($levelB, 'pending', 20, null, '2026-07-29 15:00:00');
        $this->createWithdraw($levelB, 'rejected', 10, '2026-07-29 16:00:00');

        $dailyClaim = DailyIncomeClaim::query()->create([
            'user_id' => $levelC->id,
            'user_product_id' => $levelCProduct->id,
            'product_id' => $productT2->id,
            'claim_day_no' => 1,
            'amount' => 12,
            'claim_date' => '2026-07-30',
            'claimable_at' => '2026-07-30 12:00:00',
            'claimed_at' => '2026-07-30 12:00:00',
        ]);
        CommissionRecord::query()->create([
            'earner_user_id' => $root->id,
            'source_user_id' => $levelC->id,
            'source_user_product_id' => $levelCProduct->id,
            'daily_income_claim_id' => $dailyClaim->id,
            'product_id' => $productT2->id,
            'generation_level' => 2,
            'source_daily_income' => 12,
            'percentage' => 25,
            'amount' => 3,
            'status' => 'paid',
            'created_at' => '2026-07-30 12:00:00',
            'updated_at' => '2026-07-30 12:00:00',
        ]);
        $bonusCodeId = DB::table('bonus_codes')->insertGetId([
            'code' => 'TEST-BONUS',
            'owner_user_id' => $root->id,
            'type' => 'admin',
            'amount' => 7,
            'status' => 'used',
            'used_at' => '2026-07-30 13:00:00',
            'created_at' => '2026-07-30 13:00:00',
            'updated_at' => '2026-07-30 13:00:00',
        ]);
        BonusClaim::query()->create([
            'user_id' => $root->id,
            'bonus_code_id' => $bonusCodeId,
            'type' => 'admin',
            'amount' => 7,
            'claimed_at' => '2026-07-30 13:00:00',
        ]);
        SalaryClaim::query()->create([
            'user_id' => $root->id,
            'type' => 'weekly',
            'period_key' => '2026-W31',
            'vip_level_snapshot' => 1,
            'accumulated_recharge_snapshot' => 0,
            'active_team_size_snapshot' => 2,
            'salary_amount' => 10,
            'team_subsidy_amount' => 1,
            'total_amount' => 11,
            'claimed_at' => '2026-07-30 14:00:00',
        ]);

        $response = $this->withToken($token)
            ->getJson($this->url('2026-07-28', '2026-07-30'))
            ->assertOk()
            ->assertJsonPath('schema_version', '1.0')
            ->assertJsonPath('platform.code', 'cafebustelo')
            ->assertJsonPath('platform.name', 'CafeBustelo')
            ->assertJsonPath('timezone', 'Asia/Kuala_Lumpur')
            ->assertJsonPath('currency.code', 'MYR')
            ->assertJsonPath('currency.symbol', 'RM');

        $payload = $response->json();
        $selected = $payload['sections']['operation_selected'];
        $operation = $payload['sections']['operation_overview'];
        $finance = $payload['sections']['finance_overview'];
        $requests = $payload['sections']['request_overview'];
        $users = $payload['sections']['user_overview'];
        $chart = $payload['sections']['finance_chart'];

        $this->assertSame(235.0, $payload['common']['cash_in']);
        $this->assertSame(56.0, $payload['common']['cash_out']);
        $this->assertSame(179.0, $payload['common']['net_cash_flow']);
        $this->assertSame(3, $payload['common']['new_users']);
        $this->assertSame(3, $payload['common']['total_users']);
        $this->assertSame(4, $payload['common']['order_count']);

        $this->assertSame(2, $selected['first_recharge_users']);
        $this->assertSame(2, $selected['transfer_referral_users']);
        $this->assertSame(2, $selected['valid_members']);
        $this->assertSame(100.0, $selected['transfer_referral_rate']);
        $this->assertSame(300.0, $selected['upgrade_amount']);

        $this->assertSame(240.0, $operation['total_recharge']);
        $this->assertSame(65.0, $operation['total_balance']);
        $this->assertSame(1, $operation['invalid_members']);
        $this->assertSame(10.0, $operation['invalid_members_total_recharge']);
        $this->assertSame(6.0, $operation['invalid_members_total_withdraw']);
        $this->assertSame(3, $operation['today_registered']);
        $this->assertSame(1, $operation['expired_product_member_count']);

        $this->assertSame(500.0, $finance['product_purchase_volume']);
        $this->assertSame(12.0, $finance['daily_income_paid']);
        $this->assertSame(3.0, $finance['commissions_paid']);
        $this->assertSame(7.0, $finance['bonus_paid']);
        $this->assertSame(11.0, $finance['salary_subsidy_paid']);

        $this->assertSame(1, $requests['pending_recharge_count']);
        $this->assertSame(30.0, $requests['pending_recharge_amount']);
        $this->assertSame(1, $requests['pending_withdraw_count']);
        $this->assertSame(20.0, $requests['pending_withdraw_amount']);
        $this->assertSame(1, $requests['rejected_recharge_count']);
        $this->assertSame(1, $requests['rejected_withdraw_count']);

        $this->assertSame(3, $users['active_user_products']);
        $this->assertSame(1, $users['expired_user_products']);
        $this->assertSame(40.0, $users['recharge_wallet_balance']);
        $this->assertSame(25.0, $users['withdrawable_wallet_balance']);
        $this->assertSame(1, $users['virtual_users']);

        $this->assertSame(['2026-07-28', '2026-07-29', '2026-07-30'], $chart['labels']);
        $this->assertSame([0.0, 110.0, 125.0], $chart['approved_recharge']);
        $this->assertSame([0.0, 50.0, 6.0], $chart['approved_withdraw']);
        $this->assertSame([0.0, 60.0, 119.0], $chart['net']);

        $this->assertIsFloat($payload['common']['cash_in']);
        $this->assertIsInt($payload['common']['new_users']);
        $this->assertIsString($payload['generated_at']);

        $requiredSections = [
            'operation_selected',
            'operation_overview',
            'finance_overview',
            'request_overview',
            'user_overview',
            'finance_chart',
        ];
        $this->assertSame($requiredSections, array_keys($payload['sections']));

        $sensitiveKeys = [
            'password',
            'phone',
            'email',
            'bank_account_no',
            'payment_account_no',
            'receipt_path',
            'receipt_url',
            'token',
            'access_token',
            'admin_remark',
            'user_id',
        ];
        $json = $response->getContent();

        foreach ($sensitiveKeys as $key) {
            $this->assertDoesNotMatchRegularExpression(
                '/"'.preg_quote($key, '/').'"\s*:/',
                $json,
            );
        }

        $this->assertNotNull($rootProduct);
        $this->assertNotNull($levelBProduct);
    }

    public function test_create_client_command_grants_only_the_read_ability(): void
    {
        $this->artisan('dashboard-api:create-client', ['name' => 'Central Dashboard Production'])
            ->expectsOutputToContain('cannot be retrieved again')
            ->assertSuccessful();

        $client = DashboardApiClient::query()->sole();
        $token = $client->tokens()->sole();

        $this->assertTrue($client->is_active);
        $this->assertSame(['dashboard:read'], $token->abilities);
        $this->assertNotEmpty($token->token);
        $this->assertSame(64, strlen($token->token));
    }

    private function createClientToken(
        array $abilities = ['dashboard:read'],
        bool $active = true,
    ): array {
        $client = DashboardApiClient::query()->create([
            'name' => 'Test dashboard client',
            'is_active' => $active,
        ]);

        return [
            $client,
            $client->createToken('test', $abilities)->plainTextToken,
        ];
    }

    private function createUser(
        bool $isVirtual = false,
        string $status = 'active',
        ?User $referrer = null,
        string $createdAt = '2026-07-30 10:00:00',
    ): User {
        $this->userSequence++;
        $user = User::query()->create([
            'name' => 'Dashboard User '.$this->userSequence,
            'username' => 'dashboard_user_'.$this->userSequence,
            'password' => 'password',
            'status' => $status,
            'is_virtual' => $isVirtual,
            'referrer_id' => $referrer?->id,
        ]);
        $user->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        return $user;
    }

    private function createProduct(string $code): Product
    {
        return Product::query()->create([
            'name' => $code.' Product',
            'code' => $code,
            'price' => 100,
            'daily_income' => 10,
            'duration_days' => 30,
            'upgrade_bonus' => 0,
            'invite_bonus' => 0,
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }

    private function createUserProduct(
        User $user,
        Product $product,
        float $price,
        string $status,
        string $purchasedAt,
        string $expiresAt = '2026-08-29 10:00:00',
    ): UserProduct {
        return UserProduct::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_snapshot' => $price,
            'daily_income_snapshot' => 10,
            'duration_days_snapshot' => 30,
            'purchased_at' => $purchasedAt,
            'next_claim_at' => $purchasedAt,
            'expires_at' => $expiresAt,
            'status' => $status,
        ]);
    }

    private function createWallets(
        User $user,
        float $rechargeBalance,
        float $withdrawableBalance,
    ): void {
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_RECHARGE,
            'balance' => $rechargeBalance,
            'locked_balance' => 0,
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'type' => Wallet::TYPE_WITHDRAWABLE,
            'balance' => $withdrawableBalance,
            'locked_balance' => 0,
        ]);
    }

    private function createRecharge(
        User $user,
        string $status,
        float $amount,
        ?string $reviewedAt,
        ?string $createdAt = null,
    ): RechargeRequest {
        $request = RechargeRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => $status,
            'reviewed_at' => $reviewedAt,
        ]);

        if ($createdAt !== null) {
            $request->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        return $request;
    }

    private function createWithdraw(
        User $user,
        string $status,
        float $amount,
        ?string $reviewedAt,
        ?string $createdAt = null,
    ): WithdrawRequest {
        $request = WithdrawRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'fee_amount' => 0,
            'net_amount' => $amount,
            'status' => $status,
            'reviewed_at' => $reviewedAt,
        ]);

        if ($createdAt !== null) {
            $request->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->saveQuietly();
        }

        return $request;
    }

    private function url(string $from, string $to, string $mode = 'exclude_virtual'): string
    {
        return self::ENDPOINT.'?'.http_build_query([
            'date_from' => $from,
            'date_to' => $to,
            'virtual_mode' => $mode,
        ]);
    }
}
