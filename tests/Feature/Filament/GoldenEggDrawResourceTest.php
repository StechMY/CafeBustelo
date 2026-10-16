<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\GoldenEggDraws\GoldenEggDrawResource;
use App\Filament\Resources\GoldenEggDraws\Pages\ListGoldenEggDraws;
use App\Filament\Resources\UserProducts\UserProductResource;
use App\Models\AdminUser;
use App\Models\GoldenEggDraw;
use App\Models\GoldenEggPrize;
use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\User;
use App\Models\UserProduct;
use App\Services\ProductPurchaseService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PDO;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoldenEggDrawResourceTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is unavailable.');
        }
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        parent::setUp();
        app()->setLocale('en');
        Http::fake(fn () => Http::response([], 503));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_draw_resource_is_read_only_and_list_and_detail_require_their_own_permissions(): void
    {
        $draw = $this->draw($this->user());
        $admin = $this->admin([]);
        $url = GoldenEggDrawResource::getUrl('view', ['record' => $draw]);

        $this->get(GoldenEggDrawResource::getUrl())->assertForbidden();
        $this->get($url)->assertForbidden();
        $admin->givePermissionTo('ViewAny:GoldenEggDraw');
        $this->get(GoldenEggDrawResource::getUrl())->assertOk();
        $this->get($url)->assertForbidden();
        $admin->givePermissionTo('View:GoldenEggDraw');
        $this->get($url)->assertOk();

        $this->assertSame(['index', 'view'], array_keys(GoldenEggDrawResource::getPages()));
        $this->assertSame(['viewAny', 'view'], config('filament-shield.resources.manage')[GoldenEggDrawResource::class]);
    }

    public function test_support_only_sees_draws_from_their_managed_downline(): void
    {
        $managed = $this->user();
        $allowed = $this->draw($this->user($managed));
        $blocked = $this->draw($this->user());
        $admin = $this->admin();
        $admin->update(['managed_user_id' => $managed->id]);
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));

        $this->assertSame([$allowed->id], GoldenEggDrawResource::getEloquentQuery()->pluck('golden_egg_draws.id')->all());
        $this->get(GoldenEggDrawResource::getUrl('view', ['record' => $allowed]))->assertOk();
        $this->get(GoldenEggDrawResource::getUrl('view', ['record' => $blocked]))->assertNotFound();
        Livewire::test(ListGoldenEggDraws::class)
            ->assertCanSeeTableRecords([$allowed])->assertCanNotSeeTableRecords([$blocked]);
    }

    public function test_order_displays_historical_discount_snapshots_and_links_to_the_award(): void
    {
        $draw = $this->draw($this->user());
        $purchase = $this->purchase($draw->user, $draw->coupon);
        $purchase->product->update(['price' => 999]);
        GoldenEggPrize::query()->whereKey($draw->prize_id)->update(['name' => 'Changed live prize', 'discount_percent' => 80]);
        $this->admin(['ViewAny:GoldenEggDraw', 'View:GoldenEggDraw', 'ViewAny:UserProduct', 'View:UserProduct']);

        $this->get(UserProductResource::getUrl('view', ['record' => $purchase]))
            ->assertOk()->assertSee('Original price')->assertSee('Discount percentage')
            ->assertSee('Discount amount')->assertSee('Actual payment')
            ->assertSee('100.00')->assertSee('10.00')->assertSee('90.00')
            ->assertSee('Original coupon award')->assertDontSee('Changed live prize')
            ->assertSee(GoldenEggDrawResource::getUrl('view', ['record' => $draw]), false);

        $this->get(GoldenEggDrawResource::getUrl('view', ['record' => $draw]))
            ->assertOk()->assertSee('Used')->assertSee('Used on order')
            ->assertSee(UserProductResource::getUrl('view', ['record' => $purchase]), false);
    }

    public function test_legacy_orders_without_coupons_use_the_paid_snapshot_instead_of_the_current_product_price(): void
    {
        $purchase = $this->purchase($this->user());
        $purchase->update(['meta' => ['payment_wallet_type' => 'recharge']]);
        $purchase->product->update(['price' => 999]);
        $this->admin(['ViewAny:UserProduct', 'View:UserProduct']);

        $this->get(UserProductResource::getUrl('view', ['record' => $purchase]))
            ->assertOk()->assertSee('Original price')->assertSee('100.00')
            ->assertSee('No coupon')->assertSee('0.00')->assertDontSee('999.00');
    }

    public function test_a_fully_discounted_order_still_displays_its_original_price_and_zero_payment(): void
    {
        $draw = $this->draw($this->user(), percent: 100);
        $purchase = $this->purchase($draw->user, $draw->coupon);
        $this->admin(['ViewAny:UserProduct', 'View:UserProduct']);

        $this->assertSame('0.00', $purchase->price_snapshot);
        $this->get(UserProductResource::getUrl('view', ['record' => $purchase]))
            ->assertOk()->assertSee('Actual payment')->assertSee('100.00')->assertSee('0.00');
    }

    public function test_coupon_status_filters_exclude_cash_and_missing_coupon_records(): void
    {
        $user = $this->user();
        $used = $this->draw($user);
        $this->purchase($user, $used->coupon);
        $unused = $this->draw($user);
        $cash = $this->draw($user, 'cash');
        $missing = $this->draw($user);
        $missing->coupon->delete();
        $this->admin();

        $this->assertSame('Credited', GoldenEggDrawResource::couponStatus($cash));
        $this->assertSame('Coupon record missing', GoldenEggDrawResource::couponStatus($missing->fresh()));
        Livewire::test(ListGoldenEggDraws::class)
            ->assertCanSeeTableRecords([$used, $unused, $cash, $missing])
            ->filterTable('coupon_status', 'used')
            ->assertCanSeeTableRecords([$used])->assertCanNotSeeTableRecords([$unused, $cash, $missing])
            ->removeTableFilter('coupon_status')->filterTable('coupon_status', 'unused')
            ->assertCanSeeTableRecords([$unused])->assertCanNotSeeTableRecords([$used, $cash, $missing]);
    }

    public function test_cross_links_only_appear_with_permission_to_view_the_destination(): void
    {
        $draw = $this->draw($this->user());
        $purchase = $this->purchase($draw->user, $draw->coupon);
        $admin = $this->admin();
        $purchaseUrl = UserProductResource::getUrl('view', ['record' => $purchase]);
        $drawUrl = GoldenEggDrawResource::getUrl('view', ['record' => $draw]);

        $this->get($drawUrl)->assertOk()->assertDontSee($purchaseUrl, false);
        $admin->givePermissionTo(Permission::findOrCreate('View:UserProduct', 'admin'));
        $this->get($drawUrl)->assertOk()->assertDontSee($purchaseUrl, false);
        $admin->givePermissionTo(Permission::findOrCreate('ViewAny:UserProduct', 'admin'));
        $this->get($drawUrl)->assertOk()->assertSee($purchaseUrl, false);
        $admin->revokePermissionTo('View:GoldenEggDraw');
        $this->get($purchaseUrl)->assertOk()->assertDontSee($drawUrl, false);
    }

    public function test_used_coupons_stay_used_when_the_order_is_deleted(): void
    {
        $draw = $this->draw($this->user());
        $purchase = $this->purchase($draw->user, $draw->coupon);
        $purchase->delete();
        $this->admin(['ViewAny:GoldenEggDraw', 'View:GoldenEggDraw', 'ViewAny:UserProduct', 'View:UserProduct']);
        $url = GoldenEggDrawResource::getUrl('view', ['record' => $draw]);

        $this->get($url)->assertOk()->assertSee('Used')->assertSee('Deleted')
            ->assertDontSee(UserProductResource::getUrl('view', ['record' => $purchase]), false);
        $this->assertSame($purchase->id, $draw->fresh()->coupon->purchase->id);
        $purchase->forceDelete();
        $this->assertSame('Used', GoldenEggDrawResource::couponStatus($draw->fresh()));
        $this->get($url)->assertOk()->assertSee('Not recorded');
    }

    public function test_missing_discount_snapshots_are_not_replaced_with_the_live_product_price(): void
    {
        $draw = $this->draw($this->user());
        $purchase = $this->purchase($draw->user, $draw->coupon);
        $purchase->update(['meta' => ['coupon_id' => $draw->coupon->id]]);
        $purchase->product->update(['price' => 999]);
        $this->admin(['ViewAny:UserProduct', 'View:UserProduct']);

        $this->get(UserProductResource::getUrl('view', ['record' => $purchase]))
            ->assertOk()->assertSee('Not recorded')->assertSee('90.00')->assertDontSee('999.00');
    }

    private function admin(array $permissions = ['ViewAny:GoldenEggDraw', 'View:GoldenEggDraw']): AdminUser
    {
        $admin = AdminUser::query()->create([
            'username' => 'draw-admin-'.(++$this->sequence), 'password' => 'password', 'status' => 'active',
        ]);
        foreach ($permissions as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function user(?User $referrer = null): User
    {
        $number = ++$this->sequence;

        return User::query()->create([
            'username' => 'draw-user-'.$number, 'name' => 'Draw User '.$number, 'password' => 'password',
            'referral_code' => 'DRAW'.$number, 'referrer_id' => $referrer?->id,
            'status' => 'active', 'order_status' => true, 'is_virtual' => false,
        ]);
    }

    private function draw(User $user, string $type = 'coupon', int $percent = 10): GoldenEggDraw
    {
        $draw = GoldenEggDraw::query()->create([
            'user_id' => $user->id, 'prize_id' => GoldenEggPrize::query()->firstOrFail()->id,
            'request_id' => (string) Str::uuid(), 'egg_number' => 5,
            'prize_name' => $type === 'coupon' ? 'Original coupon award' : 'Cash award',
            'prize_type' => $type, 'amount' => $type === 'cash' ? 25 : 0,
            'discount_percent' => $type === 'coupon' ? $percent : 0, 'is_virtual' => false,
        ]);
        if ($type === 'coupon') PurchaseCoupon::query()->create([
            'user_id' => $user->id, 'golden_egg_draw_id' => $draw->id, 'discount_percent' => $percent,
        ]);

        return $draw;
    }

    private function purchase(User $user, ?PurchaseCoupon $coupon = null): UserProduct
    {
        $user->wallets()->updateOrCreate(['type' => 'recharge'], ['balance' => 1000, 'locked_balance' => 0]);
        $product = Product::query()->create([
            'name' => 'Reconciliation Product', 'code' => 'DRAW-P'.(++$this->sequence),
            'price' => 100, 'daily_income' => 2, 'duration_days' => 10,
            'status' => 'active', 'max_purchase_per_user' => 10,
        ]);

        return app(ProductPurchaseService::class)->purchase($user, $product, couponId: $coupon?->id);
    }
}
