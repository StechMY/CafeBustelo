<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ProductPurchaseService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductDailyLimitTest extends TestCase
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

        app()->setLocale('en');
        $this->travelTo(Carbon::parse('2026-09-22 12:00:00', config('app.timezone')));
    }

    public function test_limit_is_shared_by_all_buyers_and_both_wallets(): void
    {
        $product = $this->product(2);
        $service = app(ProductPurchaseService::class);
        $service->purchase($this->user(), $product, Wallet::TYPE_RECHARGE);
        $this->assertTrue($product->hasDailyStock());
        $service->purchase($this->user(), $product, Wallet::TYPE_WITHDRAWABLE);
        $this->assertFalse($product->hasDailyStock());

        $buyer = $this->user();
        try {
            $service->purchase($buyer, $product);
            $this->fail('The purchase exceeded the daily limit.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('products.product_sold_out'), $exception->getMessage());
        }

        $this->assertDatabaseCount('user_products', 2);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertEquals(1000, $buyer->wallets()->where('type', Wallet::TYPE_RECHARGE)->value('balance'));

        $otherProduct = $this->product(1, 'OTHER');
        $service->purchase($buyer, $otherProduct);
        $this->assertDatabaseCount('user_products', 3);
    }

    #[DataProvider('soldOutLimits')]
    public function test_sold_out_product_remains_visible_and_direct_requests_are_rejected(?int $dailyLimit, ?int $stock): void
    {
        $this->travelTo(Carbon::parse('2026-09-30 15:00:00', config('app.timezone')));
        $product = $this->product($dailyLimit);
        $product->update([
            'available_days' => [3],
            'available_from' => '2026-09-30 15:00:00',
            'available_until' => '2026-09-30 18:00:00',
            'stock_quantity' => $stock,
        ]);
        app(ProductPurchaseService::class)->purchase($this->user(), $product);
        $product->refresh();
        $buyer = $this->user();
        $error = __($dailyLimit === 1 ? 'products.product_sold_out' : 'products.product_not_available_now');

        $this->actingAs($buyer)->get('/products')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee(__('products.sold_out'))
            ->assertDontSee(__('products.purchase_now'));

        $this->from('/products')->post("/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertRedirect('/products')
            ->assertSessionHas('error', $error);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $product->id)
            ->assertJsonPath('data.tabs.0.count', 1)
            ->assertJsonPath('data.products_by_type.'.$product->product_type.'.0.id', $product->id)
            ->assertJsonPath('data.items.0.daily_limit', $dailyLimit)
            ->assertJsonPath('data.items.0.daily_limit_reached', $dailyLimit === 1)
            ->assertJsonPath('data.items.0.remaining_stock', $stock === null ? null : 0)
            ->assertJsonPath('data.items.0.is_purchasable_now', false)
            ->assertJsonPath('data.items.0.can_purchase', false);
        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_WITHDRAWABLE])
            ->assertStatus(400)
            ->assertJsonPath('message', $error);
        $this->assertDatabaseCount('user_products', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertEquals(1000, $buyer->wallets()->where('type', Wallet::TYPE_RECHARGE)->value('balance'));
        $this->assertEquals(1000, $buyer->wallets()->where('type', Wallet::TYPE_WITHDRAWABLE)->value('balance'));
    }

    public static function soldOutLimits(): array
    {
        return [
            'daily limit reached' => [1, null],
            'total stock exhausted' => [null, 1],
            'stock exhausted before daily limit' => [5, 1],
            'both limits reached' => [1, 1],
        ];
    }

    public function test_sold_out_products_still_follow_weekday_time_and_active_status(): void
    {
        $product = $this->product(null);
        $product->update([
            'available_days' => [3],
            'available_from' => '2026-09-30 15:00:00',
            'available_until' => '2026-09-30 18:00:00',
            'stock_quantity' => 0,
        ]);
        $buyer = $this->user();
        $this->actingAs($buyer);
        Sanctum::actingAs($buyer);

        foreach (['2026-09-30 14:59:59', '2026-09-30 18:00:01', '2026-10-01 15:00:00'] as $time) {
            $this->travelTo(Carbon::parse($time, config('app.timezone')));
            $this->get('/products')->assertOk()->assertDontSee($product->name);
            $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data.items');
        }

        $this->travelTo(Carbon::parse('2026-09-30 15:00:00', config('app.timezone')));
        $product->update(['status' => 'inactive']);
        $this->get('/products')->assertOk()->assertDontSee($product->name);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_limit_resets_at_system_midnight_and_counts_completed_or_deleted_purchases(): void
    {
        $product = $this->product(1);
        $buyer = $this->user();
        $service = app(ProductPurchaseService::class);

        $this->travelTo(Carbon::parse('2026-09-22 23:59:59', config('app.timezone')));
        $purchase = $service->purchase($buyer, $product);
        $purchase->update(['status' => 'completed']);
        $purchase->delete();
        $this->assertFalse($product->hasDailyStock());

        $this->travelTo(Carbon::parse('2026-09-23 00:00:00', config('app.timezone')));
        $this->assertTrue($product->isPurchasableNow());
        $service->purchase($buyer, $product);
        $this->assertFalse($product->hasDailyStock());
        $this->assertDatabaseCount('user_products', 2);
    }

    public function test_empty_limit_is_unlimited_and_current_setting_is_rechecked_when_buying(): void
    {
        $product = $this->product(null);
        $service = app(ProductPurchaseService::class);
        $buyer = $this->user();

        for ($i = 0; $i < 3; $i++) {
            $service->purchase($buyer, $product);
        }
        $this->assertTrue($product->hasDailyStock());

        Product::query()->whereKey($product->id)->update(['daily_limit' => 2]);
        try {
            $service->purchase($buyer, $product);
            $this->fail('The latest daily limit was not checked.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('products.product_sold_out'), $exception->getMessage());
        }

        Product::query()->whereKey($product->id)->update(['daily_limit' => null]);
        $service->purchase($buyer, $product);
        $this->assertDatabaseCount('user_products', 4);
    }

    public function test_failed_payment_does_not_consume_a_daily_slot(): void
    {
        $product = $this->product(1);
        $buyer = $this->user();
        $buyer->wallets()->update(['balance' => 0]);

        try {
            app(ProductPurchaseService::class)->purchase($buyer, $product);
            $this->fail('The payment should fail without funds.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Insufficient wallet balance.', $exception->getMessage());
        }

        $this->assertTrue($product->hasDailyStock());
        $this->assertDatabaseCount('user_products', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        app(ProductPurchaseService::class)->purchase($this->user(), $product);
        $this->assertFalse($product->hasDailyStock());
    }

    public function test_daily_limit_does_not_override_total_stock_or_per_user_limits(): void
    {
        $product = $this->product(10);
        $product->update(['stock_quantity' => 2, 'max_purchase_per_user' => 1]);
        $buyer = $this->user();
        $service = app(ProductPurchaseService::class);
        $service->purchase($buyer, $product);

        try {
            $service->purchase($buyer, $product);
            $this->fail('The per-user limit should still apply.');
        } catch (RuntimeException $exception) {
            $this->assertSame(__('products.max_purchase_limit_reached', ['limit' => 1]), $exception->getMessage());
        }

        $service->purchase($this->user(), $product);
        $this->assertTrue($product->fresh()->hasDailyStock());
        $this->assertFalse($product->fresh()->isPurchasableNow());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('products.product_not_available_now'));
        $service->purchase($this->user(), $product);
    }

    public function test_admin_can_create_edit_and_clear_daily_limit(): void
    {
        $this->actingAsAdmin();
        Livewire::test(CreateProduct::class)->fillForm([
            'name' => 'Daily Product', 'code' => 'DAILY',
            'price' => 50, 'daily_income' => 1, 'duration_days' => 10,
            'daily_limit' => 5,
        ])->call('create')->assertHasNoFormErrors();
        $product = Product::query()->where('code', 'DAILY')->firstOrFail();
        $this->assertSame(5, $product->daily_limit);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertFormSet(['daily_limit' => 5])
            ->fillForm(['daily_limit' => 10])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(10, $product->fresh()->daily_limit);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['daily_limit' => ''])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->daily_limit);
    }

    #[DataProvider('invalidLimits')]
    public function test_admin_rejects_invalid_limits(int|float $limit): void
    {
        $this->actingAsAdmin();
        $product = $this->product(5);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['daily_limit' => $limit])
            ->call('save')->assertHasFormErrors(['daily_limit']);
        $this->assertSame(5, $product->fresh()->daily_limit);
    }

    public static function invalidLimits(): array
    {
        return [[0], [-1], [1.5], [4294967296]];
    }

    private function actingAsAdmin(): void
    {
        $admin = AdminUser::query()->create([
            'username' => 'daily-admin', 'password' => 'password', 'status' => 'active',
        ]);
        foreach (['ViewAny:Product', 'View:Product', 'Create:Product', 'Update:Product'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
    }

    private function user(): User
    {
        $number = User::query()->count() + 1;
        $user = User::query()->create([
            'name' => 'Daily Buyer '.$number, 'username' => 'daily-buyer-'.$number,
            'phone' => '600000000'.$number, 'password' => 'password',
            'referral_code' => 'DAILY'.$number, 'status' => 'active', 'order_status' => true,
            'accumulated_recharge' => 0,
        ]);
        foreach ([Wallet::TYPE_RECHARGE, Wallet::TYPE_WITHDRAWABLE] as $type) {
            $user->wallets()->create(['type' => $type, 'balance' => 1000, 'locked_balance' => 0]);
        }

        return $user;
    }

    private function product(?int $limit, string $code = 'DAILY'): Product
    {
        return Product::query()->create([
            'name' => 'Daily Product '.$code, 'code' => $code,
            'price' => 50, 'daily_income' => 1, 'duration_days' => 10,
            'max_purchase_per_user' => 10, 'daily_limit' => $limit, 'status' => 'active',
        ]);
    }
}
