<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\User;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Services\ProductPurchaseService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductVipLevelRequirementTest extends TestCase
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

        foreach ([3, 1, 2] as $level) {
            VipLevel::query()->create([
                'level' => $level,
                'name' => 'LV'.$level,
                'required_accumulated_recharge' => ($level - 1) * 100,
                'required_active_team_size' => 0,
                'status' => 'active',
            ]);
        }
    }

    #[DataProvider('purchaseRules')]
    public function test_purchase_enforces_minimum_level_before_any_side_effects(
        int $level,
        ?int $minimum,
        string $walletType,
        bool $allowed,
    ): void {
        $user = $this->user($level);
        $product = $this->product($minimum);
        $failure = null;

        try {
            app(ProductPurchaseService::class)->purchase($user, $product, $walletType);
        } catch (RuntimeException $exception) {
            $failure = $exception->getMessage();
        }

        $this->assertSame($allowed ? null : __('products.vip_level_required', ['level' => $minimum]), $failure);
        $this->assertEquals($allowed ? 950 : 1000, $user->wallets()->where('type', $walletType)->value('balance'));
        $this->assertDatabaseCount('user_products', $allowed ? 1 : 0);
        $this->assertDatabaseCount('wallet_transactions', $allowed ? 1 : 0);
        $this->assertSame($allowed ? 1 : 0, $product->fresh()->sold_quantity);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public static function purchaseRules(): array
    {
        return [
            'existing unrestricted product' => [1, null, Wallet::TYPE_RECHARGE, true],
            'LV1 minimum' => [1, 1, Wallet::TYPE_RECHARGE, true],
            'below minimum with recharge wallet' => [1, 2, Wallet::TYPE_RECHARGE, false],
            'below minimum with withdrawable wallet' => [1, 2, Wallet::TYPE_WITHDRAWABLE, false],
            'equal to minimum' => [2, 2, Wallet::TYPE_RECHARGE, true],
            'above minimum' => [3, 2, Wallet::TYPE_WITHDRAWABLE, true],
            'LV3 product blocks LV2' => [2, 3, Wallet::TYPE_RECHARGE, false],
        ];
    }

    public function test_web_and_api_show_requirement_and_reject_direct_purchase_requests(): void
    {
        $user = $this->user(1);
        $product = $this->product(2);

        $this->actingAs($user)
            ->get('/products')
            ->assertOk()
            ->assertSee(__('products.vip_level_required', ['level' => 2]))
            ->assertSee(__('products.vip_level_insufficient'));

        $this->from('/products')
            ->post("/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertRedirect('/products')
            ->assertSessionHas('error', __('products.vip_level_required', ['level' => 2]));

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.items.0.min_vip_level', 2)
            ->assertJsonPath('data.items.0.meets_vip_level_requirement', false)
            ->assertJsonPath('data.items.0.can_purchase', false);

        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_WITHDRAWABLE])
            ->assertStatus(400)
            ->assertJsonPath('message', __('products.vip_level_required', ['level' => 2]));

        $this->assertDatabaseCount('user_products', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_existing_admin_assigned_level_is_used_and_rechecked_at_purchase(): void
    {
        $user = $this->user(1);
        $user->update(['display_vip_level' => 3]);
        $product = $this->product(3);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.items.0.meets_vip_level_requirement', true)
            ->assertJsonPath('data.items.0.can_purchase', true);

        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])
            ->assertCreated();

        User::query()->whereKey($user->id)->update(['display_vip_level' => 1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('products.vip_level_required', ['level' => 3]));
        app(ProductPurchaseService::class)->purchase($user, $product);
    }

    public function test_admin_can_create_change_and_clear_the_requirement(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Tiered Product',
                'code' => 'TIERED',
                'price' => 50,
                'daily_income' => 1,
                'duration_days' => 10,
                'min_vip_level' => 2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('code', 'TIERED')->firstOrFail();
        $this->assertSame(2, $product->min_vip_level);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertFormSet(['min_vip_level' => 2])
            ->fillForm(['min_vip_level' => 3])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(3, $product->fresh()->min_vip_level);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['min_vip_level' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($product->fresh()->min_vip_level);
    }

    public function test_admin_cannot_save_an_unknown_level(): void
    {
        $this->actingAsAdmin();
        $product = $this->product(2);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['min_vip_level' => 99])
            ->call('save')
            ->assertHasFormErrors(['min_vip_level']);

        $this->assertSame(2, $product->fresh()->min_vip_level);
    }

    private function actingAsAdmin(): void
    {
        $admin = AdminUser::query()->create([
            'username' => 'product-admin',
            'password' => 'password',
            'status' => 'active',
        ]);

        foreach (['ViewAny:Product', 'View:Product', 'Create:Product', 'Update:Product'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
    }

    private function user(int $level): User
    {
        $user = User::query()->create([
            'name' => 'Tier User',
            'username' => 'tier-user',
            'phone' => '6000000001',
            'password' => 'password',
            'referral_code' => 'TIERUSER',
            'status' => 'active',
            'order_status' => true,
            'accumulated_recharge' => ($level - 1) * 100,
        ]);

        foreach ([Wallet::TYPE_RECHARGE, Wallet::TYPE_WITHDRAWABLE] as $type) {
            $user->wallets()->create(['type' => $type, 'balance' => 1000, 'locked_balance' => 0]);
        }

        return $user;
    }

    private function product(?int $minimum): Product
    {
        return Product::query()->create([
            'name' => 'Tiered Product',
            'code' => 'TIERED',
            'price' => 50,
            'daily_income' => 1,
            'duration_days' => 10,
            'stock_quantity' => 10,
            'status' => 'active',
            'min_vip_level' => $minimum,
        ]);
    }
}
