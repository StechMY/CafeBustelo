<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\TreasureChestClaims\Pages\ManageTreasureChestClaims;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\Setting;
use App\Models\TreasureKeyEntry;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Services\GoldenEggService;
use App\Services\ProductPurchaseService;
use App\Services\TreasureChestService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TreasureChestActivityTest extends TestCase
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
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-01 12:00:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_defaults_closed_with_nine_chests_and_authentication_required(): void
    {
        $this->getJson('/api/v1/treasure-chest')->assertUnauthorized();
        $this->postJson('/api/v1/treasure-chest/claim', ['chest_number' => 1])->assertUnauthorized();
        $this->actingAs($this->user())->get('/treasure-chest')->assertOk()->assertSee('data-chest="9"', false);
        $this->postJson('/treasure-chest/claim', ['chest_number' => 1])->assertStatus(400);
        $this->getJson('/api/v1/app-config')->assertOk()->assertJsonPath('data.modules.treasure_chest', true);
        $this->assertDatabaseCount('treasure_chest_claims', 0);
    }

    #[DataProvider('products')]
    public function test_each_product_earns_keys_for_every_purchase_by_old_invitee(string $code, int $price, int $keys, string $type): void
    {
        $referrer = $this->user();
        $buyer = $this->user(['referrer_id' => $referrer->id, 'created_at' => now()->subYear()]);
        $product = $this->product($code, $price, $type);
        $this->fund($buyer);
        // Purchases before activation do not count, and do not prevent later purchases from counting.
        app(ProductPurchaseService::class)->purchase($buyer, $product);
        $this->open();
        $first = app(ProductPurchaseService::class)->purchase($buyer, $product);
        app(TreasureChestService::class)->givePurchaseKeys($buyer, $first);
        app(ProductPurchaseService::class)->purchase($buyer, $product);
        $this->assertSame($keys * 2, app(TreasureChestService::class)->totalKeys($referrer));
        $this->assertSame(0, app(TreasureChestService::class)->totalKeys($buyer));
        $this->assertDatabaseCount('treasure_key_entries', 2);
    }

    public static function products(): array
    {
        return [
            ['T1', 450000, 1, Product::TYPE_NORMAL],
            ['W2', 1950000, 2, Product::TYPE_NORMAL],
            ['WS-1', 2300000, 3, Product::TYPE_WEEKEND_SPECIAL],
            ['WP-1', 3600000, 4, Product::TYPE_WEEKEND_SPECIAL],
            ['WS-2', 5500000, 6, Product::TYPE_WEEKEND_SPECIAL],
            ['WP-2', 6850000, 8, Product::TYPE_WEEKEND_SPECIAL],
            ['W3', 9550000, 12, Product::TYPE_NORMAL],
            ['W4', 19500000, 20, Product::TYPE_NORMAL],
            ['W5', 38500000, 40, Product::TYPE_NORMAL],
            ['PRODUCTION-WEEKEND', 2300000, 3, Product::TYPE_WEEKEND_SPECIAL],
        ];
    }

    public function test_purchase_window_uses_vietnam_time_and_includes_start_but_excludes_end(): void
    {
        $referrer = $this->user();
        $buyer = $this->user(['referrer_id' => $referrer->id]);
        $this->fund($buyer);
        $this->open();
        Setting::setValue(TreasureChestService::SETTING_START, '2026-11-01 00:00:00');
        Setting::setValue(TreasureChestService::SETTING_END, '2026-11-02 00:00:00');
        $product = $this->product();
        foreach ([['2026-10-31 16:59:59', 0], ['2026-10-31 17:00:00', 1], ['2026-11-01 16:59:59', 2], ['2026-11-01 17:00:00', 2]] as [$utc, $expected]) {
            $this->travelTo(\Illuminate\Support\Carbon::parse($utc, 'UTC')->timezone('Asia/Ho_Chi_Minh'));
            app(ProductPurchaseService::class)->purchase($buyer, $product);
            $this->assertSame($expected, app(TreasureChestService::class)->totalKeys($referrer));
        }
        $this->actingAs($referrer)->postJson('/treasure-chest/claim', ['chest_number' => 1])->assertStatus(400);
    }

    public function test_bonus_keys_unlock_later_chests_and_each_reward_is_paid_only_once(): void
    {
        $this->open();
        $user = $this->user();
        $this->keys($user, 6);
        $this->actingAs($user);
        $this->postJson('/treasure-chest/claim', ['chest_number' => 5])->assertStatus(400);
        $this->postJson('/treasure-chest/claim', ['chest_number' => 4])->assertOk()->assertJsonPath('data.activity.total_keys', 8);
        $this->postJson('/treasure-chest/claim', ['chest_number' => 5])->assertOk()->assertJsonPath('data.activity.total_keys', 13);
        $this->postJson('/treasure-chest/claim', ['chest_number' => 6])->assertOk();
        $this->postJson('/treasure-chest/claim', ['chest_number' => 7])->assertStatus(400);
        $this->keys($user, 19); // 25 purchased keys + 7 bonus keys = 32.
        $this->postJson('/treasure-chest/claim', ['chest_number' => 8])->assertOk()->assertJsonPath('data.activity.total_keys', 40);
        foreach ([1, 2, 3, 7, 9] as $number) {
            $this->postJson('/treasure-chest/claim', ['chest_number' => $number])->assertOk();
        }
        Setting::setValue(TreasureChestService::SETTING_ENABLED, false);
        foreach (range(1, 9) as $number) {
            // Lost responses remain safely retryable even after the activity closes.
            $this->postJson('/treasure-chest/claim', ['chest_number' => $number, 'amount' => 9999999])->assertOk();
        }
        $this->assertSame(60, app(TreasureChestService::class)->totalKeys($user));
        $this->assertDatabaseCount('treasure_chest_claims', 9);
        $this->assertDatabaseCount('purchase_coupons', 3);
        $this->assertDatabaseCount('wallet_transactions', 5);
        $this->assertEquals(1020000, $user->wallets()->where('type', Wallet::TYPE_WITHDRAWABLE)->value('balance'));
        $this->assertSame([10, 15, 20], PurchaseCoupon::query()->orderBy('discount_percent')->pluck('discount_percent')->map(fn ($v) => (int) $v)->all());
    }

    public function test_api_cannot_claim_for_another_user_or_spoof_keys_and_retries_are_idempotent(): void
    {
        $this->open();
        $owner = $this->user();
        $other = $this->user();
        $this->keys($owner, 1);
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/treasure-chest/claim', ['chest_number' => 1, 'user_id' => $owner->id, 'total_keys' => 40, 'is_virtual' => true])->assertStatus(400);
        $this->postJson('/api/v1/treasure-chest/claim', ['chest_number' => 10])->assertUnprocessable();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/treasure-chest')->assertOk()->assertJsonCount(9, 'data.chests');
        foreach (range(1, 2) as $attempt) {
            $this->postJson('/api/v1/treasure-chest/claim', ['chest_number' => 1])->assertOk()->assertJsonPath('data.activity.total_keys', 1);
        }
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $owner->id, 'amount' => 70000]);
    }

    public function test_chest_coupon_works_at_checkout_and_keys_use_original_weekend_price(): void
    {
        $this->open();
        $referrer = $this->user();
        $buyer = $this->user(['referrer_id' => $referrer->id]);
        $this->keys($buyer, 12);
        app(TreasureChestService::class)->claim($buyer, 6);
        $coupon = PurchaseCoupon::query()->sole();
        $this->assertNull($coupon->golden_egg_draw_id);
        $this->fund($buyer);
        $product = $this->product('CUSTOM-WEEKEND', 2300000, Product::TYPE_WEEKEND_SPECIAL);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/products/'.$product->id.'/purchase', ['wallet_type' => Wallet::TYPE_RECHARGE, 'coupon_id' => $coupon->id])->assertCreated();
        $this->assertDatabaseHas('user_products', ['user_id' => $buyer->id, 'price_snapshot' => 2070000]);
        $this->assertNotNull($coupon->fresh()->used_at);
        $this->assertSame(3, app(TreasureChestService::class)->totalKeys($referrer));
        $this->postJson('/api/v1/products/'.$product->id.'/purchase', ['wallet_type' => Wallet::TYPE_RECHARGE, 'coupon_id' => $coupon->id])->assertStatus(400);
        $this->assertSame(1, UserProduct::query()->count());
    }

    public function test_failed_purchase_does_not_grant_keys(): void
    {
        $this->open();
        $referrer = $this->user();
        $buyer = $this->user(['referrer_id' => $referrer->id]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/v1/products/'.$this->product()->id.'/purchase', ['wallet_type' => Wallet::TYPE_RECHARGE])->assertStatus(400);
        $this->assertDatabaseCount('treasure_key_entries', 0);
        $this->assertDatabaseCount('user_products', 0);
    }

    public function test_combined_reward_failure_rolls_back_cash_coupon_and_claim(): void
    {
        $this->open();
        $user = $this->user();
        $this->keys($user, 40);
        PurchaseCoupon::creating(function (): void {
            throw new RuntimeException('Coupon unavailable');
        });
        try {
            app(TreasureChestService::class)->claim($user, 9);
            $this->fail('Expected failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Coupon unavailable', $exception->getMessage());
        } finally {
            PurchaseCoupon::flushEventListeners();
        }
        $this->assertDatabaseCount('treasure_chest_claims', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('purchase_coupons', 0);
        $this->assertSame(40, app(TreasureChestService::class)->totalKeys($user));
    }

    public function test_unlisted_products_do_not_earn_keys(): void
    {
        $this->open();
        $referrer = $this->user();
        $buyer = $this->user(['referrer_id' => $referrer->id]);
        $this->fund($buyer);
        app(ProductPurchaseService::class)->purchase($buyer, $this->product('UNLISTED'));
        $this->assertDatabaseCount('treasure_key_entries', 0);
    }

    #[DataProvider('virtualReferrals')]
    public function test_virtual_accounts_earn_purchase_keys_and_claim_rewards_once(bool $virtualReferrer, bool $virtualBuyer): void
    {
        $this->open();
        $referrer = $this->user(['is_virtual' => $virtualReferrer]);
        $buyer = $this->user(['referrer_id' => $referrer->id, 'is_virtual' => $virtualBuyer]);
        $this->fund($buyer);
        $product = $this->product('W4', 19500000);
        $purchase = app(ProductPurchaseService::class)->purchase($buyer, $product);
        app(TreasureChestService::class)->givePurchaseKeys($buyer, $purchase);
        app(ProductPurchaseService::class)->purchase($buyer, $product);
        $this->assertSame(40, app(TreasureChestService::class)->totalKeys($referrer));
        $this->assertDatabaseCount('treasure_key_entries', 2);
        $this->actingAs($referrer)->getJson('/api/v1/treasure-chest')->assertOk()
            ->assertJsonPath('data.enabled', true)->assertJsonPath('data.status', 'active');
        foreach (range(1, 2) as $attempt) {
            $this->postJson('/treasure-chest/claim', ['chest_number' => 9])->assertOk()
                ->assertJsonPath('data.activity.total_keys', 60);
        }
        $this->assertDatabaseCount('treasure_chest_claims', 1);
        $this->assertDatabaseCount('purchase_coupons', 1);
        $this->assertEquals(500000, $referrer->wallets()->where('type', Wallet::TYPE_WITHDRAWABLE)->value('balance'));
    }

    public static function virtualReferrals(): array
    {
        return [[true, false], [false, true], [true, true]];
    }

    public function test_admin_settings_require_permission_and_validate_dates(): void
    {
        $admin = AdminUser::query()->create(['username' => 'chest-admin', 'password' => 'password', 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageTreasureChestClaims::class)->assertForbidden();
        $admin->givePermissionTo(Permission::findOrCreate('Manage:TreasureChest', 'admin'));
        $manager = Livewire::test(ManageTreasureChestClaims::class)->assertOk();
        $manager->callAction('activity_settings', [
            'enabled' => true, 'starts_at' => '2026-11-01 00:00:00', 'ends_at' => '2026-10-31 00:00:00',
        ])->assertHasActionErrors(['ends_at']);
        $this->assertFalse(Setting::bool(TreasureChestService::SETTING_ENABLED));
        Livewire::test(ManageTreasureChestClaims::class)->callAction('activity_settings', [
            'enabled' => true, 'starts_at' => '2026-11-01 00:00:00', 'ends_at' => '2026-11-02 00:00:00',
        ])->assertHasNoActionErrors();
        $this->assertTrue(app(TreasureChestService::class)->isActiveAt());
        $this->assertSame('2026-11-01 00:00:00', Setting::getValue(TreasureChestService::SETTING_START));
    }

    public function test_dashboard_settings_open_member_activity_and_share_settings_with_claims_page(): void
    {
        $admin = AdminUser::query()->create(['username' => 'chest-dashboard', 'password' => 'password', 'status' => 'active']);
        $admin->givePermissionTo(Permission::findOrCreate('Manage:TreasureChest', 'admin'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Dashboard::class)
            ->assertActionVisible('treasureChestSettings')
            ->callAction('treasureChestSettings', [
                'enabled' => true, 'starts_at' => '2026-11-01 00:00:00', 'ends_at' => '2026-10-31 00:00:00',
            ])->assertHasActionErrors(['ends_at']);
        $this->assertFalse(Setting::bool(TreasureChestService::SETTING_ENABLED));

        Livewire::test(Dashboard::class)->callAction('treasureChestSettings', [
            'enabled' => true, 'starts_at' => '2026-11-01 00:00:00', 'ends_at' => null,
        ])->assertHasNoActionErrors();
        $this->assertFalse(Setting::bool(GoldenEggService::SETTING_ENABLED));
        $user = $this->user();
        $this->keys($user, 1);
        $this->actingAs($user, 'web')->getJson('/api/v1/treasure-chest')->assertOk()
            ->assertJsonPath('data.status', 'active')->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.chests.0.claimable', true);

        Livewire::test(ManageTreasureChestClaims::class)->mountAction('activity_settings')
            ->assertActionDataSet(['enabled' => true, 'starts_at' => '2026-11-01 00:00:00', 'ends_at' => null])
            ->setActionData(['enabled' => false])->callMountedAction()->assertHasNoActionErrors();
        Livewire::test(Dashboard::class)->mountAction('treasureChestSettings')
            ->assertActionDataSet(['enabled' => false]);
        $this->getJson('/api/v1/treasure-chest')->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_dashboard_settings_cannot_be_used_without_treasure_permission(): void
    {
        $admin = AdminUser::query()->create(['username' => 'chest-viewer', 'password' => 'password', 'status' => 'active']);
        $admin->givePermissionTo(Permission::findOrCreate('Manage:GoldenEgg', 'admin'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Dashboard::class)->assertActionHidden('treasureChestSettings')
            ->call('mountAction', 'treasureChestSettings')->assertActionNotMounted('treasureChestSettings');
        $this->assertFalse(Setting::bool(TreasureChestService::SETTING_ENABLED));
    }

    #[DataProvider('unavailableStatuses')]
    public function test_unavailable_activity_explains_the_reason_on_web_and_api(bool $enabled, bool $virtual, ?string $start, ?string $end, string $status): void
    {
        Setting::setValue(TreasureChestService::SETTING_ENABLED, $enabled);
        Setting::setValue(TreasureChestService::SETTING_START, $start ?? '');
        Setting::setValue(TreasureChestService::SETTING_END, $end ?? '');
        $user = $this->user(['is_virtual' => $virtual]);
        $this->keys($user, 40);
        $message = __('treasure_chest.'.$status);
        $this->actingAs($user)->get('/treasure-chest')->assertOk()->assertSee($message);
        $this->getJson('/api/v1/treasure-chest')->assertOk()
            ->assertJsonPath('data.status', $status)->assertJsonPath('data.status_message', $message)
            ->assertJsonPath('data.enabled', false)->assertJsonPath('data.chests.0.claimable', false);
        $this->postJson('/treasure-chest/claim', ['chest_number' => 1])->assertStatus(400)->assertJsonPath('message', $message);
        $this->assertDatabaseCount('treasure_chest_claims', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public static function unavailableStatuses(): array
    {
        return [
            'switch off' => [false, false, null, null, 'closed'],
            'before start' => [true, false, '2026-11-01 12:00:01', null, 'scheduled'],
            'at end' => [true, false, null, '2026-11-01 12:00:00', 'ended'],
            'virtual account before start' => [true, true, '2026-11-01 12:00:01', null, 'scheduled'],
            'virtual account after end' => [true, true, null, '2026-11-01 12:00:00', 'ended'],
            'virtual account while closed' => [false, true, null, null, 'closed'],
        ];
    }

    private function open(): void
    {
        Setting::setValue(TreasureChestService::SETTING_ENABLED, true);
        Setting::setValue(TreasureChestService::SETTING_START, '2026-11-01 00:00:00');
    }

    private function user(array $attributes = []): User
    {
        $number = User::query()->count() + 1;

        return User::query()->create(array_replace([
            'name' => 'Chest User '.$number, 'username' => 'chest-user-'.$number,
            'password' => 'password', 'referral_code' => 'CHEST'.$number, 'status' => 'active',
            'order_status' => true, 'is_virtual' => false, 'accumulated_recharge' => 0,
        ], $attributes));
    }

    private function product(string $code = 'T1', int $price = 450000, string $type = Product::TYPE_NORMAL): Product
    {
        return Product::query()->create([
            'name' => $code, 'code' => $code, 'price' => $price, 'daily_income' => 0,
            'duration_days' => 10, 'max_purchase_per_user' => 10, 'status' => 'active', 'product_type' => $type,
        ]);
    }

    private function fund(User $user): void
    {
        $user->wallets()->create(['type' => Wallet::TYPE_RECHARGE, 'balance' => 200000000, 'locked_balance' => 0]);
    }

    private function keys(User $user, int $keys): void
    {
        TreasureKeyEntry::query()->create(['user_id' => $user->id, 'keys' => $keys]);
    }
}
