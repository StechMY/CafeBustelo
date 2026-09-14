<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\WalletTransaction;
use App\Services\CommissionService;
use App\Services\DailyIncomeClaimService;
use App\Services\PointService;
use App\Services\ProductPurchaseService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Mockery;
use PDO;
use RuntimeException;
use Tests\TestCase;

class DailyIncomeClaimBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        $connection = 'sqlite';
        $database = ':memory:';

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $database = (string) getenv('PRODUCT_CLAIM_TEST_DATABASE');

            if ($database === '') {
                $this->markTestSkipped(
                    'SQLite PDO is unavailable and PRODUCT_CLAIM_TEST_DATABASE is not set.',
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
            'app.timezone' => 'Asia/Kuala_Lumpur',
            'database.default' => $connection,
            "database.connections.{$connection}.database" => $database,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_new_purchase_expires_at_end_of_final_claim_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 12:54:00', 'Asia/Kuala_Lumpur'));

        $user = $this->createUser();
        $product = $this->createProduct(intervalHours: 24, totalClaims: 34);

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('debit')->once()->andReturn(new WalletTransaction);

        $pointService = Mockery::mock(PointService::class);
        $pointService->shouldReceive('giveUpgradeDayPurchasePoints')->once();
        $pointService->shouldReceive('giveInviteDayFirstPurchasePoints')->once();

        $userProduct = (new ProductPurchaseService($walletService, $pointService))
            ->purchase($user, $product);

        $this->assertSame('2026-07-12 12:54:00', $userProduct->next_claim_at->toDateTimeString());
        $this->assertSame('2026-08-14 23:59:59', $userProduct->expires_at->toDateTimeString());
    }

    public function test_final_claim_is_rejected_before_unlock_and_status_stays_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 12:53:00', 'Asia/Kuala_Lumpur'));

        $userProduct = $this->createFinalClaimProduct();

        $this->assertClaimRejected(
            $userProduct,
            __('daily_income.not_claimable_yet'),
        );
        $this->assertSame('active', $userProduct->fresh()->status);
    }

    public function test_final_claim_succeeds_at_exact_unlock_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 12:54:00', 'Asia/Kuala_Lumpur'));

        $this->assertFinalClaimSucceeds($this->createFinalClaimProduct());
    }

    public function test_final_claim_succeeds_later_on_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 16:31:00', 'Asia/Kuala_Lumpur'));

        $this->assertFinalClaimSucceeds($this->createFinalClaimProduct());
    }

    public function test_final_claim_succeeds_near_end_of_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 23:59:00', 'Asia/Kuala_Lumpur'));

        $this->assertFinalClaimSucceeds($this->createFinalClaimProduct());
    }

    public function test_final_claim_is_rejected_after_final_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-15 00:01:00', 'Asia/Kuala_Lumpur'));

        $this->assertClaimRejected(
            $this->createFinalClaimProduct(),
            __('daily_income.product_expired'),
        );
    }

    public function test_non_final_claim_behavior_is_unchanged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-22 12:54:00', 'Asia/Kuala_Lumpur'));

        $userProduct = $this->createUserProduct([
            'claimed_days' => 10,
            'next_claim_at' => '2026-07-22 12:54:00',
            'expires_at' => '2026-08-14 23:59:59',
        ]);

        $this->claimService()->claim($userProduct->user, $userProduct);

        $userProduct->refresh();

        $this->assertSame(11, $userProduct->claimed_days);
        $this->assertSame(0, $userProduct->missed_days);
        $this->assertSame('active', $userProduct->status);
        $this->assertSame('2026-07-23 12:54:00', $userProduct->next_claim_at->toDateTimeString());
    }

    public function test_hourly_product_final_claim_has_end_of_day_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 16:31:00', 'Asia/Kuala_Lumpur'));

        $userProduct = $this->createUserProduct([
            'duration_days_snapshot' => 1,
            'claim_interval_hours_snapshot' => 1,
            'total_claims_snapshot' => 3,
            'claimed_days' => 2,
            'purchased_at' => '2026-08-14 10:00:00',
            'next_claim_at' => '2026-08-14 13:00:00',
            'expires_at' => '2026-08-14 13:00:00',
        ], intervalHours: 1, totalClaims: 3);

        $this->assertFinalClaimSucceeds($userProduct, totalClaims: 3);
    }

    public function test_old_active_record_with_colliding_expiry_can_make_final_claim(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 16:31:00', 'Asia/Kuala_Lumpur'));

        $userProduct = $this->createFinalClaimProduct(legacyExpiry: true);

        $this->assertSame(
            $userProduct->next_claim_at->toDateTimeString(),
            $userProduct->expires_at->toDateTimeString(),
        );

        $this->assertFinalClaimSucceeds($userProduct);
    }

    public function test_status_sync_respects_effective_expiry_for_old_active_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 16:31:00', 'Asia/Kuala_Lumpur'));

        $userProduct = $this->createFinalClaimProduct(legacyExpiry: true);

        $this->artisan('products:sync-status')->assertSuccessful();
        $this->assertSame('active', $userProduct->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-08-15 00:01:00', 'Asia/Kuala_Lumpur'));

        $this->artisan('products:sync-status')->assertSuccessful();
        $this->assertSame('expired', $userProduct->fresh()->status);
    }

    private function assertFinalClaimSucceeds(UserProduct $userProduct, int $totalClaims = 34): void
    {
        $claim = $this->claimService()->claim($userProduct->user, $userProduct);

        $userProduct->refresh();

        $this->assertSame($totalClaims, $userProduct->claimed_days);
        $this->assertSame('expired', $userProduct->status);
        $this->assertNull($userProduct->next_claim_at);
        $this->assertSame($totalClaims, $claim->claim_day_no);
    }

    private function assertClaimRejected(UserProduct $userProduct, string $expectedMessage): void
    {
        try {
            $this->claimService(shouldCredit: false)->claim($userProduct->user, $userProduct);
            $this->fail('Expected the claim to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame($expectedMessage, $exception->getMessage());
        }
    }

    private function claimService(bool $shouldCredit = true): DailyIncomeClaimService
    {
        $walletService = Mockery::mock(WalletService::class);
        $commissionService = Mockery::mock(CommissionService::class);

        if ($shouldCredit) {
            $walletService->shouldReceive('credit')->once()->andReturn(new WalletTransaction);
            $commissionService->shouldReceive('payFromDailyIncomeClaim')->once();
        } else {
            $walletService->shouldNotReceive('credit');
            $commissionService->shouldNotReceive('payFromDailyIncomeClaim');
        }

        return new DailyIncomeClaimService($walletService, $commissionService);
    }

    private function createFinalClaimProduct(bool $legacyExpiry = false): UserProduct
    {
        return $this->createUserProduct([
            'claimed_days' => 33,
            'next_claim_at' => '2026-08-14 12:54:00',
            'expires_at' => $legacyExpiry
                ? '2026-08-14 12:54:00'
                : '2026-08-14 23:59:59',
        ]);
    }

    private function createUserProduct(
        array $overrides = [],
        int $intervalHours = 24,
        int $totalClaims = 34,
    ): UserProduct {
        $user = $this->createUser();
        $product = $this->createProduct($intervalHours, $totalClaims);

        return UserProduct::query()->create(array_merge([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'price_snapshot' => 100,
            'daily_income_snapshot' => 10,
            'duration_days_snapshot' => 34,
            'claim_interval_hours_snapshot' => $intervalHours,
            'total_claims_snapshot' => $totalClaims,
            'product_type_snapshot' => $intervalHours < 24 ? Product::TYPE_HOURLY : Product::TYPE_NORMAL,
            'extra_points_snapshot' => 0,
            'claimed_days' => 0,
            'missed_days' => 0,
            'purchased_at' => '2026-07-11 12:54:00',
            'next_claim_at' => '2026-07-12 12:54:00',
            'expires_at' => '2026-08-14 23:59:59',
            'status' => 'active',
        ], $overrides));
    }

    private function createUser(): User
    {
        $this->sequence++;

        return User::query()->create([
            'name' => 'Claim User '.$this->sequence,
            'username' => 'claim_user_'.$this->sequence,
            'password' => Hash::make('password'),
            'status' => 'active',
            'order_status' => true,
        ]);
    }

    private function createProduct(int $intervalHours, int $totalClaims): Product
    {
        $this->sequence++;

        return Product::query()->create([
            'name' => 'Claim Product '.$this->sequence,
            'code' => 'CLAIM-'.$this->sequence,
            'product_type' => $intervalHours < 24 ? Product::TYPE_HOURLY : Product::TYPE_NORMAL,
            'price' => 100,
            'daily_income' => 10,
            'duration_days' => 34,
            'claim_interval_hours' => $intervalHours,
            'total_claims' => $totalClaims,
            'status' => 'active',
        ]);
    }
}
