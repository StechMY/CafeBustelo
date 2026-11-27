<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\RedPacketIssuanceRecords;
use App\Filament\Resources\RedPacketPrizes\Pages\ManageRedPacketPrizes;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\AdminUser;
use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\RedPacketDraw;
use App\Models\RedPacketEntry;
use App\Models\RedPacketPrize;
use App\Models\RedPacketPrizeOverride;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Services\ProductPurchaseService;
use App\Services\RedPacketService;
use App\Services\TreasureChestService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PDO;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response([], 503));
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

    public function test_direct_first_purchases_qualify_regardless_of_registration_and_weekend_first_purchase_is_excluded(): void
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
        $this->assertSame(2, app(RedPacketService::class)->remainingChances($owner));
        $this->assertSame(0, app(RedPacketService::class)->remainingChances($grandparent));
    }

    public function test_backfill_restores_only_missing_invites_and_local_daily_bonuses_once(): void
    {
        $migration = require database_path('migrations/2026_11_27_000001_backfill_red_packet_first_purchase_invitations.php');
        $service = app(RedPacketService::class);
        $owner = $this->user();
        $other = $this->user();
        $member = fn () => $this->user(['referrer_id' => $owner->id, 'created_at' => now()->subYear()]);
        $product = $this->product('S1', 3500000, Product::TYPE_LIMITED_TIME);
        $this->travelTo(Carbon::parse('2026-11-14 23:59:59'));
        $repeatBuyer = $member();
        $this->purchase($repeatBuyer, $product)->delete();
        $this->travelTo(Carbon::parse('2026-11-15 00:00:00'));
        $this->open();
        foreach (range(1, 3) as $i) {
            $buyer = $member();
            $this->purchase($buyer, $product);
        }
        $buyer->update(['referrer_id' => $other->id]);
        $spent = RedPacketEntry::where('source', 'milestone_3')->sole();
        $spent->update(['used_at' => now()]);
        Setting::setValue(RedPacketService::SETTING_ENABLED, false);
        $this->travelTo(Carbon::parse('2026-11-15 23:59:59'));
        $this->purchase($repeatBuyer, $product);
        $weekendBuyer = $member();
        $this->purchase($weekendBuyer, $this->product('WP-1', 3600000, Product::TYPE_WEEKEND_SPECIAL));
        $this->purchase($weekendBuyer, $product);
        $this->purchase($this->user(['referrer_id' => $owner->id, 'is_virtual' => true]), $product);
        $this->purchase($member(), $product)->delete();
        $this->purchase($member(), $product);
        $this->travelTo(Carbon::parse('2026-11-16 00:00:00'));
        foreach (range(1, 3) as $i) {
            $this->purchase($member(), $product);
        }
        Setting::setValue(RedPacketService::SETTING_END, '2026-11-16 00:00:01');
        $this->travelTo(Carbon::parse('2026-11-16 00:00:01'));
        $this->purchase($member(), $product);
        $upgrades = RedPacketEntry::where('source', 'upgrade')->count();
        $migration->up();
        $migration->up();
        $entries = RedPacketEntry::where('user_id', $owner->id);
        $this->assertSame(8, (clone $entries)->where('earned_on', '2026-11-15')->count());
        $this->assertSame(4, (clone $entries)->where('earned_on', '2026-11-16')->count());
        $this->assertSame(8, (clone $entries)->where('source', 'invite')->count());
        $this->assertSame(2, (clone $entries)->where('source', 'milestone_3')->count());
        $this->assertSame(2, (clone $entries)->where('source', 'milestone_5')->count());
        $this->assertSame(11, $service->remainingChances($owner));
        $this->assertSame(8, (clone $entries)->where('is_backfill', true)->count());
        $this->assertSame(4, (clone $entries)->where('is_backfill', false)->count());
        $this->assertTrue($spent->used_at->equalTo($spent->fresh()->used_at));
        $this->assertSame(0, $service->remainingChances($other));
        $this->assertSame($upgrades, RedPacketEntry::where('source', 'upgrade')->count());
    }

    public function test_issuance_report_counts_granted_chances_by_user_method_and_source(): void
    {
        $admin = $this->assignmentAdmin();
        $this->get(RedPacketIssuanceRecords::getUrl())->assertForbidden();
        $admin->givePermissionTo(Permission::findOrCreate('ViewAny:RedPacketDraw', 'admin'));
        $owner = $this->user(['username' => 'ledger-owner']);
        $other = $this->user(['username' => 'second-recipient']);
        foreach (['invite', 'milestone_3', 'milestone_5', 'milestone_5'] as $source) {
            RedPacketEntry::create(['user_id' => $owner->id, 'source' => $source, 'award_key' => (string) Str::uuid(),
                'earned_on' => today(), 'used_at' => now(), 'is_backfill' => true]);
        }
        $this->entry($owner); // Legacy row without an issuance marker.
        RedPacketEntry::create(['user_id' => $owner->id, 'source' => 'invite', 'award_key' => 'invite:123',
            'earned_on' => today(), 'is_backfill' => false]);
        RedPacketEntry::create(['user_id' => $other->id, 'source' => 'invite', 'award_key' => 'invite:456',
            'earned_on' => today(), 'is_backfill' => true]);

        $page = Livewire::test(RedPacketIssuanceRecords::class)->assertOk()
            ->assertCanSeeTableRecords([$owner, $other])->searchTable('ledger-owner')
            ->assertCanSeeTableRecords([$owner])->assertCanNotSeeTableRecords([$other]);
        $summary = $page->instance()->getTableRecords()->first();
        foreach (['chances' => 4, 'invites' => 1, 'bonus_three' => 1, 'bonus_five' => 2] as $column => $count) {
            $page->assertTableColumnStateSet($column, $count, $summary);
        }
        $page->filterTable('issuance', 'legacy');
        $this->assertEquals(1, $page->instance()->getTableRecords()->first()->upgrades);
        $page->filterTable('issuance', 'live');
        $this->assertEquals(1, $page->instance()->getTableRecords()->first()->invites);
        $page->filterTable('issued_at', ['from' => now()->addMinute()->toDateTimeString(), 'until' => null])
            ->assertCanNotSeeTableRecords([$owner]);

        $root = $this->user();
        $owner->update(['referrer_id' => $root->id]);
        $admin->update(['managed_user_id' => $root->id]);
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));
        Livewire::test(RedPacketIssuanceRecords::class)->assertCanSeeTableRecords([$owner])
            ->searchTable('second-recipient')->assertCanNotSeeTableRecords([$other]);
        $this->get(RedPacketIssuanceRecords::getUrl(['recipient' => $other->id]))->assertNotFound();
    }

    public function test_recipient_link_shows_filtered_purchase_snapshots_and_actual_daily_bonus_counts(): void
    {
        $admin = $this->assignmentAdmin();
        $owner = $this->user();
        $this->get(RedPacketIssuanceRecords::getUrl(['recipient' => $owner->id]))->assertForbidden();
        $admin->givePermissionTo(Permission::findOrCreate('ViewAny:RedPacketDraw', 'admin'));
        $product = $this->product('S1', 3500000);
        $product->update(['name' => 'Original limited product']);
        $this->travelTo(Carbon::parse('2026-11-15 23:59:30'));
        foreach (range(1, 5) as $index) {
            $buyer = $this->user(['referrer_id' => $owner->id]);
            $this->purchase($buyer, $product);
        }
        $this->travelTo(Carbon::parse('2026-11-16 00:01:00'));
        $this->open();
        $liveBuyer = $this->user(['referrer_id' => $owner->id]);
        $this->purchase($liveBuyer, $product);
        $this->travelTo(Carbon::parse('2026-11-17 08:30:00'));
        app(RedPacketService::class)->backfillFirstPurchaseInvitations();
        $product->update(['name' => 'Renamed after purchase']);
        UserProduct::where('user_id', $buyer->id)->sole()->delete();
        $from = '2026-11-17 08:30:00';
        $url = RedPacketIssuanceRecords::getUrl(['recipient' => $owner->id, 'method' => 'backfill', 'from' => $from]);
        Livewire::test(RedPacketIssuanceRecords::class)
            ->filterTable('issued_at', ['from' => $from, 'until' => null])->assertSeeHtml($url);
        $this->get($url)->assertOk()->assertSee($buyer->username)->assertSee('S1 · Original limited product')
            ->assertSee('2026-11-15 23:59:30')->assertDontSee('Renamed after purchase')->assertDontSee($liveBuyer->username);
        $details = Livewire::withQueryParams(['recipient' => $owner->id, 'method' => 'all'])
            ->test(RedPacketIssuanceRecords::class)->assertSee($liveBuyer->username);
        $rows = $details->instance()->getTableRecords()->getCollection();
        $this->assertCount(6, $rows->where('source', 'invite'));
        foreach (['milestone_3' => 1, 'milestone_5' => 2] as $source => $count) {
            $row = $rows->where('source', $source)->sole();
            $this->assertEquals($count, $row->chances);
            $this->assertSame('2026-11-15', $row->earned_on);
        }
        $details->filterTable('issuance', 'live')->assertSee($liveBuyer->username)->assertDontSee($buyer->username);
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
        $this->assertFalse(\App\Filament\Actions\RedPacketSettingsAction::make('settings')->isAuthorized());
        $this->get('/admin/red-packet-draws')->assertForbidden();
    }

    public function test_existing_super_admin_can_manage_activity_without_explicit_new_permissions(): void
    {
        $admin = AdminUser::create(['username' => 'rain-super-admin', 'password' => 'password', 'status' => 'active']);
        $admin->assignRole(Role::findOrCreate('super_admin', 'admin'));
        $this->assertFalse($admin->can('Manage:RedPacket'));
        $this->assertFalse($admin->can('ViewAny:RedPacketDraw'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Dashboard::class)->assertActionVisible('redPacketSettings')
            ->callAction('redPacketSettings', data: [
                'enabled' => true, 'starts_at' => '2026-11-15 00:00:00', 'ends_at' => '2026-11-30 00:00:00',
            ])->assertHasNoActionErrors();
        $this->assertTrue(Setting::bool(RedPacketService::SETTING_ENABLED));
        Livewire::test(ManageRedPacketPrizes::class)->assertActionVisible('activity_settings');
        $this->get('/admin/red-packet-draws')->assertOk();
        $policy = new \App\Policies\RedPacketDrawPolicy();
        $draw = new RedPacketDraw(['user_id' => 1]);
        $this->assertTrue($policy->view($admin, $draw));
        $this->assertFalse($policy->update($admin, $draw));
        $this->assertFalse($policy->delete($admin, $draw));
    }

    public function test_role_editor_lists_and_can_assign_red_packet_permissions(): void
    {
        $admin = AdminUser::create(['username' => 'role-editor', 'password' => 'password', 'status' => 'active']);
        foreach (['ViewAny:Role', 'View:Role', 'Update:Role'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $role = Role::findOrCreate('rain-operator', 'admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $prizePermissions = ['ViewAny:RedPacketPrize', 'View:RedPacketPrize', 'Update:RedPacketPrize'];
        $drawPermissions = ['ViewAny:RedPacketDraw', 'View:RedPacketDraw'];

        Livewire::test(\BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole::class, ['record' => $role->id])
            ->assertSee('Manage Red Packet Activity')->assertSee('RedPacketPrize')->assertSee('RedPacketDraw')
            ->fillForm([
                'custom_permissions_tab' => ['Manage:RedPacket'],
                \App\Filament\Resources\RedPacketPrizes\RedPacketPrizeResource::class => $prizePermissions,
                \App\Filament\Resources\RedPacketDraws\RedPacketDrawResource::class => $drawPermissions,
            ])->call('save')->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing(['Manage:RedPacket', ...$prizePermissions, ...$drawPermissions], $role->fresh()->permissions->pluck('name')->all());
        $admin->assignRole($role);
        $admin->unsetRelation('roles')->unsetRelation('permissions');
        Livewire::test(Dashboard::class)->assertActionVisible('redPacketSettings');
        Livewire::test(ManageRedPacketPrizes::class)->assertActionVisible('activity_settings');
        $this->get('/admin/red-packet-draws')->assertOk();
    }

    public function test_virtual_next_prize_bypasses_zero_limits_once_and_keeps_assignment_audit(): void
    {
        $this->open();
        $admin = $this->assignmentAdmin();
        $virtual = $this->user(['is_virtual' => true]);
        $cash = $this->onlyPrize('cash', 20);
        $iphone = RedPacketPrize::where('slot', 1)->sole();
        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('setNextRedPacketPrize')->table($virtual), ['prize_id' => $iphone->id])
            ->assertHasNoActionErrors();
        $assignment = RedPacketPrizeOverride::sole();
        $request = (string) Str::uuid();
        $service = app(RedPacketService::class);
        $draw = $service->draw($virtual, $request);
        $this->assertSame($iphone->id, $draw->prize_id);
        $this->assertSame(0, $iphone->fresh()->stock);
        $this->assertSame($draw->id, $assignment->fresh()->draw_id);
        $this->assertNotNull($assignment->fresh()->used_at);
        $this->assertSame($admin->id, $draw->prizeOverride->admin->id);
        $this->assertSame($cash->id, $service->draw($virtual, (string) Str::uuid())->prize_id);
        $this->assertSame(20, $cash->fresh()->stock);

        $service->setNextPrize($admin, $virtual, $iphone->id);
        $this->assertSame($draw->id, $service->draw($virtual, $request)->id);
        $this->assertSame(1, RedPacketPrizeOverride::pending()->count());
        $this->assertSame($iphone->id, $service->draw($virtual, (string) Str::uuid())->prize_id);
        $this->assertSame(0, RedPacketPrizeOverride::pending()->count());
    }

    public function test_virtual_assignment_can_be_replaced_cancelled_and_validated(): void
    {
        $this->open();
        $admin = $this->assignmentAdmin();
        $virtual = $this->user(['is_virtual' => true]);
        $prizes = RedPacketPrize::orderBy('slot')->get();
        $service = app(RedPacketService::class);
        $service->setNextPrize($admin, $virtual, $prizes[0]->id);
        $service->setNextPrize($admin, $virtual, $prizes[1]->id);
        $this->assertSame($prizes[1]->id, RedPacketPrizeOverride::pending()->sole()->prize_id);
        $this->assertNotNull(RedPacketPrizeOverride::first()->cancelled_at);
        try {
            $service->setNextPrize($admin, $virtual, 999999);
            $this->fail('An unknown prize must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('prize_id', $exception->errors());
        }
        Livewire::test(ListUsers::class)
            ->mountAction(TestAction::make('setNextRedPacketPrize')->table($virtual))
            ->assertActionDataSet(['prize_id' => $prizes[1]->id])
            ->setActionData(['prize_id' => null])->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame(0, RedPacketPrizeOverride::pending()->count());
        $this->assertSame(2, RedPacketPrizeOverride::whereNotNull('cancelled_at')->count());
    }

    public function test_assignments_use_normal_cash_and_coupon_fulfilment_and_rollback_on_failure(): void
    {
        $this->open();
        $admin = $this->assignmentAdmin();
        $virtual = $this->user(['is_virtual' => true]);
        RedPacketPrize::query()->update(['weight' => 0, 'stock' => 0]);
        $cash = RedPacketPrize::where('type', 'cash')->firstOrFail();
        $coupon = RedPacketPrize::where('type', 'coupon')->sole();
        $service = app(RedPacketService::class);
        $service->setNextPrize($admin, $virtual, $cash->id);
        $walletService = \Mockery::mock(\App\Services\WalletService::class);
        $walletService->shouldReceive('credit')->once()->andThrow(new \RuntimeException('Simulated wallet failure'));
        try {
            (new RedPacketService($walletService))->draw($virtual, (string) Str::uuid());
            $this->fail('A failed payout must roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated wallet failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('red_packet_draws', 0);
        $this->assertSame(1, RedPacketPrizeOverride::pending()->count());
        $service->draw($virtual, (string) Str::uuid());
        $this->assertEquals($cash->amount, $virtual->wallets()->where('type', Wallet::TYPE_WITHDRAWABLE)->value('balance'));
        $service->setNextPrize($admin, $virtual, $coupon->id);
        $draw = $service->draw($virtual, (string) Str::uuid());
        $this->assertDatabaseHas('purchase_coupons', ['user_id' => $virtual->id, 'red_packet_draw_id' => $draw->id, 'discount_percent' => $coupon->discount_percent]);
        $this->assertSame(0, $cash->fresh()->stock);
        $this->assertSame(0, $coupon->fresh()->stock);
    }

    public function test_virtual_user_actions_follow_their_own_dashboard_switches(): void
    {
        $admin = $this->assignmentAdmin();
        $virtual = $this->user(['is_virtual' => true]);
        $real = $this->user();
        foreach ([false, true] as $rain) {
            foreach ([false, true] as $chests) {
                Setting::setValue(RedPacketService::SETTING_ENABLED, $rain);
                Setting::setValue(TreasureChestService::SETTING_ENABLED, $chests);
                $page = Livewire::test(ListUsers::class);
                foreach (['setNextRedPacketPrize' => $rain, 'addTreasureKeys' => $chests] as $action => $enabled) {
                    $page->{$enabled ? 'assertActionVisible' : 'assertActionHidden'}(TestAction::make($action)->table($virtual));
                    $page->assertActionHidden(TestAction::make($action)->table($real));
                }
            }
        }
        Setting::setValue(RedPacketService::SETTING_START, '2026-11-16 00:00:00');
        Livewire::test(ListUsers::class)->assertActionVisible(TestAction::make('setNextRedPacketPrize')->table($virtual));
        $service = app(RedPacketService::class);
        $service->setNextPrize($admin, $virtual, RedPacketPrize::first()->id);
        try {
            $service->draw($virtual, (string) Str::uuid());
            $this->fail('A pending assignment must not bypass activity dates.');
        } catch (\DomainException $exception) {
            $this->assertSame(__('red_packet.closed'), $exception->getMessage());
        }
        $this->assertSame(1, RedPacketPrizeOverride::pending()->count());
    }

    public function test_assignment_rechecks_permissions_scope_switch_and_virtual_status(): void
    {
        $this->open();
        $admin = $this->assignmentAdmin();
        $virtual = $this->user(['is_virtual' => true]);
        $service = app(RedPacketService::class);
        $prize = RedPacketPrize::first();
        $reject = function () use ($service, $admin, $virtual, $prize): void {
            try {
                $service->setNextPrize($admin, $virtual, $prize->id);
                $this->fail('Unauthorized prize assignment must be rejected.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        };
        foreach (['Manage:RedPacket', 'Update:User'] as $permission) {
            $admin->revokePermissionTo($permission);
            Livewire::test(ListUsers::class)->assertActionHidden(TestAction::make('setNextRedPacketPrize')->table($virtual));
            $reject();
            $admin->givePermissionTo($permission);
        }
        Setting::setValue(RedPacketService::SETTING_ENABLED, false);
        $reject();
        $this->open();
        User::whereKey($virtual->id)->update(['is_virtual' => false]);
        $reject();
        User::whereKey($virtual->id)->update(['is_virtual' => true]);
        $root = $this->user();
        $admin->update(['managed_user_id' => $root->id]);
        $admin->assignRole(Role::findOrCreate('Support', 'admin'));
        $reject();
        $this->assertDatabaseCount('red_packet_prize_overrides', 0);
    }

    public function test_pending_assignment_is_not_used_for_a_real_user_or_changed_by_customer_input(): void
    {
        $this->open();
        $admin = $this->assignmentAdmin();
        $user = $this->user(['is_virtual' => true]);
        $cash = $this->onlyPrize('cash', 20);
        $iphone = RedPacketPrize::where('slot', 1)->sole();
        app(RedPacketService::class)->setNextPrize($admin, $user, $iphone->id);
        $user->update(['is_virtual' => false]);
        $this->entry($user);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/red-packet/draw', ['request_id' => (string) Str::uuid(), 'prize_id' => $iphone->id])
            ->assertOk()->assertJsonPath('data.prize.prize_name', $cash->name)
            ->assertJsonMissingPath('data.prize.prize_override')->assertJsonMissingPath('data.prize.admin_user_id');
        $this->assertSame(19, $cash->fresh()->stock);
        $this->assertSame(1, RedPacketPrizeOverride::pending()->count());
        $this->assertNull(RedPacketDraw::sole()->prizeOverride);
    }

    public function test_assignment_form_cannot_submit_after_dashboard_switch_is_turned_off(): void
    {
        $this->open();
        $this->assignmentAdmin();
        $virtual = $this->user(['is_virtual' => true]);
        $page = Livewire::test(ListUsers::class)
            ->mountAction(TestAction::make('setNextRedPacketPrize')->table($virtual))
            ->setActionData(['prize_id' => RedPacketPrize::first()->id]);
        Setting::setValue(RedPacketService::SETTING_ENABLED, false);
        $page->callMountedAction();
        $this->assertDatabaseCount('red_packet_prize_overrides', 0);
    }

    private function assignmentAdmin(): AdminUser
    {
        $admin = AdminUser::create(['username' => 'prize-operator', 'password' => 'password', 'status' => 'active']);
        foreach (['ViewAny:User', 'Update:User', 'Manage:RedPacket', 'Manage:TreasureChest'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
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
