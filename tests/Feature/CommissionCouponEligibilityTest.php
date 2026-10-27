<?php

namespace Tests\Feature;

use App\Models\CommissionLevel;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\Product;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommissionCouponEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is unavailable.');
        }

        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();
    }

    #[DataProvider('eligibilityCases')]
    public function test_commission_uses_purchase_time_original_prices(
        int $earnerPaid,
        ?int $earnerOriginal,
        int $sourcePaid,
        ?int $sourceOriginal,
        bool $eligible,
        string $earnerStatus = 'active',
        string $productStatus = 'active',
        bool $deleted = false,
    ): void {
        $earner = User::query()->create([
            'name' => 'Earner', 'username' => 'earner', 'password' => 'password',
            'status' => $earnerStatus,
        ]);
        $source = User::query()->create([
            'name' => 'Source', 'username' => 'source', 'password' => 'password',
            'status' => 'active', 'referrer_id' => $earner->id,
        ]);
        CommissionLevel::query()->create([
            'level' => 1, 'name' => 'B Level', 'rate_percent' => 11, 'is_active' => true,
        ]);

        // Catalog prices may change after purchase; eligibility must use snapshots.
        $product = Product::query()->create([
            'name' => 'W2', 'code' => 'W2', 'price' => 3000000,
            'daily_income' => 101400, 'duration_days' => 35, 'status' => 'active',
        ]);
        $earnerProduct = $this->purchase($earner, $product, $earnerPaid, $earnerOriginal);
        $earnerProduct->update(['status' => $productStatus]);
        if ($deleted) {
            $earnerProduct->delete();
        }

        $sourceProduct = $this->purchase($source, $product, $sourcePaid, $sourceOriginal);
        $claim = DailyIncomeClaim::query()->create([
            'user_id' => $source->id, 'user_product_id' => $sourceProduct->id,
            'product_id' => $product->id, 'claim_day_no' => 1, 'amount' => 101400,
            'claim_date' => now()->toDateString(), 'claimable_at' => now(), 'claimed_at' => now(),
        ]);

        $service = app(CommissionService::class);
        $service->payFromDailyIncomeClaim($source, $sourceProduct, $claim);
        $service->payFromDailyIncomeClaim($source, $sourceProduct, $claim);

        $this->assertDatabaseCount('commission_records', $eligible ? 1 : 0);
        $this->assertDatabaseCount('wallet_transactions', $eligible ? 1 : 0);
        if ($eligible) {
            $commission = CommissionRecord::query()->sole();
            $transaction = WalletTransaction::query()->sole();
            $this->assertSame('11154.00', $commission->amount);
            $this->assertSame($earner->id, $commission->earner_user_id);
            $this->assertSame($claim->id, $commission->daily_income_claim_id);
            $this->assertSame('commission', $transaction->type);
            $this->assertSame($commission->id, $transaction->reference_id);
            $this->assertSame('11154.00', $transaction->amount);
            $this->assertSame('11154.00', Wallet::query()->sole()->balance);
        }
    }

    public static function eligibilityCases(): array
    {
        return [
            'discounted upline W2' => [1657500, 1950000, 1950000, 1950000, true],
            'different discounts on same tier' => [1657500, 1950000, 1852500, 1950000, true],
            'free upline product retains tier' => [0, 1950000, 1950000, 1950000, true],
            'discounted source does not lower tier' => [1600000, null, 975000, 1950000, false],
            'free source does not lower tier' => [450000, null, 0, 1950000, false],
            'legacy same tier' => [1950000, null, 1950000, null, true],
            'legacy higher upline' => [2600000, null, 1950000, 1950000, true],
            'lower upline before upgrade' => [1600000, null, 1950000, 1950000, false],
            'inactive upline' => [1657500, 1950000, 1950000, 1950000, false, 'inactive'],
            'expired upline product' => [1657500, 1950000, 1950000, 1950000, false, 'active', 'expired'],
            'deleted upline product' => [1657500, 1950000, 1950000, 1950000, false, 'active', 'active', true],
        ];
    }

    private function purchase(User $user, Product $product, int $paid, ?int $original): UserProduct
    {
        return UserProduct::query()->create([
            'user_id' => $user->id, 'product_id' => $product->id,
            'price_snapshot' => $paid, 'daily_income_snapshot' => 101400,
            'duration_days_snapshot' => 35, 'claimed_days' => 0, 'missed_days' => 0,
            'purchased_at' => now()->subDay(), 'next_claim_at' => now(),
            'expires_at' => now()->addDays(34), 'status' => 'active',
            'meta' => $original === null ? null : ['original_price' => $original],
        ]);
    }
}
