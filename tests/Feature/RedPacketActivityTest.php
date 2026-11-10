<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\RedPacketPrizes\Pages\ManageRedPacketPrizes;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\RedPacketDraw;
use App\Models\RedPacketEntry;
use App\Models\RedPacketPrize;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Services\ProductPurchaseService;
use App\Services\RedPacketService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PDO;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RedPacketActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) $this->markTestSkipped('SQLite PDO driver is unavailable.');
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
            putenv("{$key}={$value}"); $_ENV[$key] = $_SERVER[$key] = $value;
        }
        parent::setUp();
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $this->beforeApplicationDestroyed(fn () => date_default_timezone_set($timezone));
        config(['app.timezone' => 'Asia/Ho_Chi_Minh']);
        \Illuminate\Support\Facades\Storage::fake('do_spaces');
        app()->setLocale('en');
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00', 'Asia/Ho_Chi_Minh'));
    }

    public function test_defaults_authentication_and_activity_entry_visibility(): void
    {
        $this->assertDatabaseCount('red_packet_prizes', 6);
        $this->assertDatabaseHas('red_packet_prizes', ['slot' => 1, 'weight' => 0, 'stock' => 0]);
        $this->getJson('/api/v1/red-packet')->assertUnauthorized();
        $this->postJson('/api/v1/red-packet/draw', ['request_id' => (string) Str::uuid()])->assertUnauthorized();
        $this->get('/red-packet/history')->assertRedirect(route('login'));
        $user = $this->user();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('href="'.route('red-packet.index').'"', false);
        $this->get('/red-packet')->assertOk()->assertSee('data-red-packet', false);
        $this->open();
        $this->get('/dashboard')->assertOk()->assertSee('href="'.route('red-packet.index').'"', false);
        Setting::setValue(RedPacketService::SETTING_END, '2026-11-15 12:00:00');
        $this->get('/dashboard')->assertOk()->assertDontSee('href="'.route('red-packet.index').'"', false);
    }

    public function test_daily_five_invites_earn_eight_then_reset_progress_but_keep_unused_chances(): void
    {
        $this->open();
        $owner = $this->user(['created_at' => now()->subYear()]);
        $product = $this->product('T1', 450000);
        foreach ([1, 2, 4, 5, 8, 9] as $expected) {
            $buyer = $this->user(['referrer_id' => $owner->id]);
            $purchase = $this->purchase($buyer, $product);
            app(RedPacketService::class)->handlePurchase($buyer, $purchase);
            $this->assertSame($expected, app(RedPacketService::class)->remainingChances($owner));
            $this->assertSame(0, app(RedPacketService::class)->remainingChances($buyer));
        }
        $tomorrowBuyer = $this->user(['referrer_id' => $owner->id]);
        $this->travelTo(Carbon::parse('2026-11-16 00:00:00', 'Asia/Ho_Chi_Minh'));
        $this->assertSame(0, app(RedPacketService::class)->activity($owner)['today_invites']);
        $this->assertSame(9, app(RedPacketService::class)->remainingChances($owner));
        $this->purchase($tomorrowBuyer, $product);
        foreach (range(1, 4) as $i) $this->purchase($this->user(['referrer_id' => $owner->id]), $product);
        $this->assertSame(17, app(RedPacketService::class)->remainingChances($owner));
        $this->assertSame(5, app(RedPacketService::class)->activity($owner)['today_invites']);
        $this->assertSame(2, RedPacketEntry::where('user_id', $owner->id)->where('source', 'milestone_3')->count());
    }

    public function test_reward_dialog_uses_red_packet_copy_in_all_four_languages(): void
    {
        config(['app.supported_locales' => ['en' => 'English', 'zh_CN' => '中文', 'vi' => 'Tiếng Việt', 'id' => 'Indonesia']]);
        $this->actingAs($this->user());
        foreach (['en' => 'Close', 'zh_CN' => '关闭', 'vi' => 'Đóng', 'id' => 'Tutup'] as $locale => $close) {
            $response = $this->withSession(['locale' => $locale])->get('/red-packet')->assertOk();
            $response->assertSee('data-rp-close>'.$close.'</button>', false)
                ->assertDontSee(__('golden_egg.close'))->assertDontSee('red_packet.', false);
            foreach (array_keys(require lang_path('en/red_packet.php')) as $key) {
                $this->assertTrue(app('translator')->hasForLocale('red_packet.'.$key, $locale), "Missing {$locale}: {$key}");
            }
        }
    }

    public function test_closed_future_and_expired_activities_hide_entries_and_block_draws_for_all_accounts(): void
    {
        foreach ([false, true] as $virtual) {
            $user = $this->user(['is_virtual' => $virtual]);
            $this->entry($user);
            $this->actingAs($user);
            foreach (['closed', 'future', 'expired'] as $state) {
                Setting::setValue(RedPacketService::SETTING_ENABLED, $state !== 'closed');
                Setting::setValue(RedPacketService::SETTING_START, $state === 'future' ? '2026-11-16 00:00:00' : '2026-11-15 00:00:00');
                Setting::setValue(RedPacketService::SETTING_END, $state === 'expired' ? '2026-11-15 12:00:00' : '');
                $link = 'href="'.route('red-packet.index').'"';
                $this->get('/dashboard')->assertOk()->assertDontSee($link, false);
                $this->assertStringNotContainsString($link, view('partials.header')->render());
                $this->assertStringNotContainsString($link, view('partials.mobile-menu')->render());
                $this->get('/red-packet')->assertOk()->assertSee(__('red_packet.closed'));
                $this->getJson('/api/v1/red-packet')->assertOk()->assertJsonPath('data.enabled', false);
                foreach (['/red-packet/draw', '/api/v1/red-packet/draw'] as $url) {
                    $this->postJson($url, ['request_id' => (string) Str::uuid()])->assertStatus(400);
                }
            }
        }
        $this->assertDatabaseCount('red_packet_draws', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(2, RedPacketEntry::whereNull('used_at')->count());
    }

    public function test_only_new_direct_first_purchases_qualify_and_weekend_first_purchase_is_excluded(): void
    {
        $this->open();
        $grandparent = $this->user();
        $owner = $this->user(['referrer_id' => $grandparent->id]);
        $old = $this->user(['referrer_id' => $owner->id, 'created_at' => Carbon::parse('2026-11-14 23:59:59')]);
        $normal = $this->product('T1', 450000);
        $this->purchase($old, $normal);
        $weekendBuyer = $this->user(['referrer_id' => $owner->id]);
        $this->purchase($weekendBuyer, $this->product('WP-1', 3600000, Product::TYPE_WEEKEND_SPECIAL));
        $this->purchase($weekendBuyer, $normal);
        $buyer = $this->user(['referrer_id' => $owner->id]);
        $first = $this->purchase($buyer, $normal);
        $first->delete();
        $this->purchase($buyer, $normal);
        $this->assertSame(1, app(RedPacketService::class)->remainingChances($owner));
        $this->assertSame(0, app(RedPacketService::class)->remainingChances($grandparent));
    }

    public function test_upgrades_compare_previous_non_weekend_original_price_not_highest_price(): void
    {
        $this->open();
        $user = $this->user();
        $t1 = $this->product('T1', 450000);
        $w2 = $this->product('W2', 1950000);
        $w3 = $this->product('W3', 9550000);
        foreach ([[$t1, 0], [$w3, 1], [$w2, 1], [$w3, 2], [$w3, 2], [$this->product('WP-2', 12000000, Product::TYPE_WEEKEND_SPECIAL), 2]] as [$product, $expected]) {
            $this->purchase($user, $product);
            $this->assertSame($expected, app(RedPacketService::class)->remainingChances($user));
        }
        $this->purchase($user, $this->product('W4', 19500000));
        $this->assertSame(3, app(RedPacketService::class)->remainingChances($user));
        $new = $this->user();
        $this->purchase($new, $w2);
        $coupon = PurchaseCoupon::create(['user_id' => $new->id, 'discount_percent' => 90]);
        app(ProductPurchaseService::class)->purchase($new, $w3, Wallet::TYPE_RECHARGE, $coupon->id);
        $this->assertSame(2, app(RedPacketService::class)->remainingChances($new));
    }

    public function test_window_excludes_end_and_disabled_purchases_are_not_backfilled(): void
    {
        $user = $this->user();
        $product = $this->product('W2', 1950000);
        $this->purchase($user, $product);
        $this->assertSame(0, app(RedPacketService::class)->remainingChances($user));
        $this->open();
        $service = app(RedPacketService::class);
        $this->assertFalse($service->isActiveAt(Carbon::parse('2026-11-14 23:59:59', 'Asia/Ho_Chi_Minh')));
        $this->assertTrue($service->isActiveAt(Carbon::parse('2026-11-15 00:00:00', 'Asia/Ho_Chi_Minh')));
        Setting::setValue(RedPacketService::SETTING_END, '2026-11-15 12:00:00');
        $this->purchase($user, $this->product('W3', 9550000));
        $this->assertSame(0, $service->remainingChances($user));
    }

    public function test_cash_draw_retry_is_idempotent_and_stock_is_never_overspent(): void
    {
        $this->open();
        $prize = $this->onlyPrize('cash', 1);
        $user = $this->user(); $this->entry($user); $this->entry($user);
        $this->actingAs($user);
        $request = ['request_id' => (string) Str::uuid()];
        $this->postJson('/red-packet/draw', $request)->assertOk()->assertJsonPath('data.remaining_chances', 1);
        $this->postJson('/red-packet/draw', $request)->assertOk()->assertJsonPath('data.remaining_chances', 1);
        $this->assertDatabaseCount('red_packet_draws', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame(0, $prize->fresh()->stock);
        $this->postJson('/red-packet/draw', ['request_id' => (string) Str::uuid()])->assertStatus(400);
        $this->assertSame(1, app(RedPacketService::class)->remainingChances($user));
        Setting::setValue(RedPacketService::SETTING_ENABLED, false);
        $this->postJson('/red-packet/draw', $request)->assertOk();
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_coupon_and_physical_rewards_have_correct_effects_and_coupons_can_be_spent(): void
    {
        $this->open(); $this->onlyPrize('coupon', 1);
        $user = $this->user(); $this->entry($user);
        $draw = app(RedPacketService::class)->draw($user, (string) Str::uuid());
        $coupon = PurchaseCoupon::where('red_packet_draw_id', $draw->id)->sole();
        $this->fund($user);
        $purchase = app(ProductPurchaseService::class)->purchase($user, $this->product('T1', 450000), Wallet::TYPE_RECHARGE, $coupon->id);
        $this->assertEquals(405000, $purchase->price_snapshot);
        $this->assertNotNull($coupon->fresh()->used_at);
        $this->onlyPrize('physical', 1); $this->entry($user);
        $physical = app(RedPacketService::class)->draw($user, (string) Str::uuid());
        $this->assertSame('physical', $physical->prize_type);
        $this->assertDatabaseCount('purchase_coupons', 1);
        $this->assertSame(0, $user->walletTransactions()->where('type', 'red_packet')->count());
    }

    public function test_virtual_has_unlimited_chances_but_respects_unavailable_prizes_without_consuming_stock(): void
    {
        $this->open(); $prize = $this->onlyPrize('cash', 1);
        $user = $this->user(['is_virtual' => true]);
        $service = app(RedPacketService::class);
        $this->assertNull($service->remainingChances($user));
        foreach (range(1, 12) as $i) $this->assertSame($prize->id, $service->draw($user, (string) Str::uuid())->prize_id);
        $this->assertSame(1, $prize->fresh()->stock);
        $this->assertDatabaseCount('red_packet_entries', 0);
        $prize->update(['stock' => 0]);
        $this->actingAs($user)->postJson('/red-packet/draw', ['request_id' => (string) Str::uuid()])->assertStatus(400);
        $prize->update(['stock' => null, 'weight' => 0]);
        $this->postJson('/red-packet/draw', ['request_id' => (string) Str::uuid()])->assertStatus(400);
        Setting::setValue(RedPacketService::SETTING_ENABLED, false);
        $prize->update(['weight' => 10]);
        $this->postJson('/red-packet/draw', ['request_id' => (string) Str::uuid()])->assertStatus(400);
    }

    public function test_api_cannot_spoof_user_or_chances_and_history_is_private(): void
    {
        $this->open(); $this->onlyPrize('cash', null);
        $owner = $this->user(); $other = $this->user(); $this->entry($owner);
        Sanctum::actingAs($other);
        $this->postJson('/api/v1/red-packet/draw', ['request_id' => (string) Str::uuid(), 'user_id' => $owner->id, 'is_virtual' => true])->assertStatus(400);
        $this->postJson('/api/v1/red-packet/draw', ['request_id' => 'invalid'])->assertUnprocessable();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/red-packet/draw', ['request_id' => (string) Str::uuid()])->assertOk();
        $this->getJson('/api/v1/red-packet/history')->assertOk()->assertJsonPath('data.draws.total', 1)->assertJsonPath('data.entries.total', 1);
        $this->actingAs($owner)->get('/red-packet/history')->assertOk()->assertSee('My activity records');
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/red-packet/history?user_id='.$owner->id)->assertOk()->assertJsonPath('data.draws.total', 0)->assertJsonPath('data.entries.total', 0);
    }

    public function test_admin_can_configure_stock_weights_and_dates_only_with_permission(): void
    {
        $admin = AdminUser::create(['username' => 'rain-admin', 'password' => 'password', 'status' => 'active']);
        foreach (['ViewAny:RedPacketPrize', 'Update:RedPacketPrize', 'Manage:RedPacket'] as $permission) $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        $this->actingAs($admin, 'admin'); Filament::setCurrentPanel(Filament::getPanel('admin'));
        $prize = RedPacketPrize::where('slot', 1)->sole();
        Livewire::test(ManageRedPacketPrizes::class)->callAction(TestAction::make('edit')->table($prize), data: [
            'name' => 'iPhone 18 Pro Max 256GB', 'type' => 'physical', 'amount' => 0, 'weight' => 0, 'stock' => 0, 'unlimited_stock' => false,
        ])->assertHasNoActionErrors();
        Livewire::test(Dashboard::class)->callAction('redPacketSettings', data: [
            'enabled' => true, 'starts_at' => '2026-11-15 00:00:00', 'ends_at' => '2026-11-30 00:00:00',
        ])->assertHasNoActionErrors();
        $this->assertTrue(Setting::bool(RedPacketService::SETTING_ENABLED));
        $admin->revokePermissionTo('Manage:RedPacket');
        Livewire::test(Dashboard::class)->assertActionHidden('redPacketSettings');
    }

    private function open(): void { Setting::setValue(RedPacketService::SETTING_ENABLED, true); }

    private function user(array $attributes = []): User
    {
        $n = User::count() + 1;
        return User::unguarded(fn () => User::create(array_replace(['name' => 'Rain User '.$n, 'username' => 'rain-user-'.$n, 'password' => 'password', 'referral_code' => 'RAIN'.$n, 'status' => 'active', 'order_status' => true, 'is_virtual' => false, 'accumulated_recharge' => 0], $attributes)));
    }

    private function product(string $code, int $price, string $type = Product::TYPE_NORMAL): Product
    {
        return Product::create(['name' => $code, 'code' => $code, 'price' => $price, 'daily_income' => 0, 'duration_days' => 10, 'max_purchase_per_user' => 30, 'status' => 'active', 'product_type' => $type]);
    }

    private function fund(User $user): void
    {
        $user->wallets()->firstOrCreate(['type' => Wallet::TYPE_RECHARGE], ['balance' => 500000000, 'locked_balance' => 0]);
    }

    private function purchase(User $user, Product $product): UserProduct
    {
        $this->fund($user);
        return app(ProductPurchaseService::class)->purchase($user, $product);
    }

    private function entry(User $user): void
    {
        RedPacketEntry::create(['user_id' => $user->id, 'award_key' => (string) Str::uuid(), 'source' => 'upgrade', 'earned_on' => today()]);
    }

    private function onlyPrize(string $type, ?int $stock): RedPacketPrize
    {
        RedPacketPrize::query()->update(['weight' => 0]);
        $prize = RedPacketPrize::where('type', $type)->where('slot', '!=', 1)->firstOrFail();
        $prize->update(['weight' => 1, 'stock' => $stock]);
        return $prize;
    }
}
