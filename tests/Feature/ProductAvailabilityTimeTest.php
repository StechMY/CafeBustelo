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
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductAvailabilityTimeTest extends TestCase
{
    use RefreshDatabase;

    private string $originalTimezone = 'UTC';

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

        $this->originalTimezone = date_default_timezone_get();
        app()->setLocale('en');
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        date_default_timezone_set(config('app.timezone'));
        $this->travelTo(Carbon::parse('2026-09-30 15:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        date_default_timezone_set($this->originalTimezone);
    }

    #[DataProvider('dailyWindows')]
    public function test_only_time_of_day_controls_availability(?string $from, ?string $until, string $now, bool $available): void
    {
        // Deliberately use unrelated dates to cover existing product settings.
        $product = new Product([
            'available_from' => $from ? '2030-01-01 '.$from : null,
            'available_until' => $until ? '2020-01-01 '.$until : null,
        ]);
        $this->travelTo(Carbon::parse($now, config('app.timezone')));

        $this->assertSame($available, $product->isWithinAvailablePeriod());
    }

    public static function dailyWindows(): array
    {
        return [
            'no limits' => [null, null, '2026-09-30 00:00:00', true],
            'before start' => ['15:00:00', '18:00:00', '2026-09-30 14:59:59', false],
            'at start' => ['15:00:00', '18:00:00', '2026-09-30 15:00:00', true],
            'at end' => ['15:00:00', '18:00:00', '2026-09-30 18:00:00', true],
            'after end' => ['15:00:00', '18:00:00', '2026-09-30 18:00:01', false],
            'reopens tomorrow' => ['15:00:00', '18:00:00', '2026-10-01 15:00:00', true],
            'start only closed' => ['15:00:00', null, '2026-09-30 14:59:59', false],
            'start only open' => ['15:00:00', null, '2026-09-30 23:59:59', true],
            'end only open' => [null, '18:00:00', '2026-09-30 00:00:00', true],
            'end only closed' => [null, '18:00:00', '2026-09-30 18:00:01', false],
            'overnight before opening' => ['22:00:00', '02:00:00', '2026-09-30 21:59:59', false],
            'overnight evening' => ['22:00:00', '02:00:00', '2026-09-30 22:00:00', true],
            'overnight midnight' => ['22:00:00', '02:00:00', '2026-10-01 00:00:00', true],
            'overnight at end' => ['22:00:00', '02:00:00', '2026-10-01 02:00:00', true],
            'overnight after end' => ['22:00:00', '02:00:00', '2026-10-01 02:00:01', false],
        ];
    }

    public function test_catalog_and_direct_purchases_use_the_daily_window_on_web_and_api(): void
    {
        $product = $this->product();
        $buyer = $this->buyer();
        $this->travelTo(Carbon::parse('2026-09-30 14:59:59', config('app.timezone')));

        $this->actingAs($buyer)->get('/products')->assertOk()->assertDontSee($product->name);
        $this->from('/products')->post("/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertRedirect('/products')
            ->assertSessionHas('error', __('products.product_not_available_now'));
        Sanctum::actingAs($buyer);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertStatus(400)->assertJsonPath('message', __('products.product_not_available_now'));
        $this->assertDatabaseCount('user_products', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);

        $this->travelTo(Carbon::parse('2026-09-30 15:00:00', config('app.timezone')));
        $this->get('/products')->assertOk()->assertSee($product->name)->assertSee('18:00')->assertDontSee('2020-01-01');
        $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonPath('data.items.0.available_from', '15:00:00')
            ->assertJsonPath('data.items.0.available_until', '18:00:00')
            ->assertJsonPath('data.items.0.available_until_label', '18:00')
            ->assertJsonPath('data.items.0.can_purchase', true);
        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertCreated();

        $this->travelTo(Carbon::parse('2026-09-30 18:00:01', config('app.timezone')));
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertStatus(400);

        $this->travelTo(Carbon::parse('2026-10-01 15:00:00', config('app.timezone')));
        $this->post("/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertSessionHas('success');
        $this->assertDatabaseCount('user_products', 2);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_weekday_and_daily_limit_are_not_reset_by_changing_the_opening_time(): void
    {
        $product = $this->product();
        $product->update(['available_days' => [3], 'daily_limit' => 1]);
        $buyer = $this->buyer();
        $service = app(ProductPurchaseService::class);
        $service->purchase($buyer, $product);
        $product->update(['available_from' => '2026-09-30 16:00:00']);
        $this->travelTo(Carbon::parse('2026-09-30 16:00:00', config('app.timezone')));
        $this->assertTrue($product->isWithinAvailablePeriod());
        $this->assertFalse($product->isPurchasableNow());
        $this->assertFalse($product->hasDailyStock());

        $this->travelTo(Carbon::parse('2026-10-01 16:00:00', config('app.timezone')));
        $this->assertTrue($product->isWithinAvailablePeriod());
        $this->assertTrue($product->hasDailyStock());
        $this->assertFalse($product->isPurchasableNow());
    }

    public function test_admin_can_create_edit_and_clear_time_only_fields(): void
    {
        $admin = AdminUser::query()->create([
            'username' => 'time-admin', 'password' => 'password', 'status' => 'active',
        ]);
        foreach (['ViewAny:Product', 'View:Product', 'Create:Product', 'Update:Product'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Livewire::test(CreateProduct::class)
            ->assertSeeHtml('type="time"')->assertDontSeeHtml('type="datetime-local"')
            ->fillForm([
                'name' => 'Daily Time Product', 'code' => 'DAILYTIME',
                'price' => 50, 'daily_income' => 1, 'duration_days' => 10,
                'available_from' => '15:00', 'available_until' => '18:00',
            ])->call('create')->assertHasNoFormErrors();
        $product = Product::query()->where('code', 'DAILYTIME')->firstOrFail();
        $this->assertSame('15:00:00', $product->available_from->format('H:i:s'));
        $this->assertSame('18:00:00', $product->available_until->format('H:i:s'));

        $product->update(['available_from' => '2020-01-01 15:00:00', 'available_until' => '2020-01-01 18:00:00']);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['available_from' => '22:00', 'available_until' => '02:00'])
            ->call('save')->assertHasNoFormErrors();
        $product->refresh();
        $this->assertSame('22:00:00', $product->available_from->format('H:i:s'));
        $this->assertSame('02:00:00', $product->available_until->format('H:i:s'));

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['available_from' => '', 'available_until' => ''])
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->available_from);
        $this->assertNull($product->fresh()->available_until);
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Recurring Time Product', 'code' => 'TIMED',
            'price' => 50, 'daily_income' => 1, 'duration_days' => 10,
            'max_purchase_per_user' => 10, 'status' => 'active',
            'available_from' => '2030-01-01 15:00:00', 'available_until' => '2020-01-01 18:00:00',
        ]);
    }

    private function buyer(): User
    {
        $user = User::query()->create([
            'name' => 'Time Buyer', 'username' => 'time-buyer',
            'phone' => '84912345678', 'password' => 'password',
            'referral_code' => 'TIMEBUYER', 'status' => 'active', 'order_status' => true,
            'accumulated_recharge' => 0,
        ]);
        $user->wallets()->create(['type' => Wallet::TYPE_RECHARGE, 'balance' => 1000, 'locked_balance' => 0]);

        return $user;
    }
}
