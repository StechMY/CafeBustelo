<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\GoldenEggPrizes\Pages\ManageGoldenEggPrizes;
use App\Http\Middleware\HumanOnly;
use App\Models\AdminUser;
use App\Models\GoldenEggEntry;
use App\Models\GoldenEggPrize;
use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\GoldenEggService;
use App\Services\OtpService;
use App\Services\ProductPurchaseService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GoldenEggActivityTest extends TestCase
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
    }

    public function test_initial_activity_has_nine_configurable_prizes_and_is_closed(): void
    {
        $this->assertDatabaseCount('golden_egg_prizes', 9);
        $this->assertFalse(Setting::bool(GoldenEggService::SETTING_ENABLED));
        $user = $this->user();
        $this->actingAs($user)->get('/golden-egg')->assertOk()
            ->assertSee('data-egg="9"', false)->assertSee('golden-egg-config');
        $this->postJson('/golden-egg/draw', $this->drawRequest() + ['is_virtual' => true])
            ->assertStatus(400)->assertJsonPath('message', __('golden_egg.closed'));
        $this->assertDatabaseCount('golden_egg_draws', 0);
    }

    #[DataProvider('registrationRoutes')]
    public function test_referral_earns_one_chance_only_after_first_successful_purchase_on_web_and_api(string $route): void
    {
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        $referrer = $this->user();
        $this->withoutMiddleware(HumanOnly::class);
        $this->partialMock(OtpService::class, function ($mock): void {
            $mock->shouldReceive('normalizePhone')->andReturnUsing(fn ($phone) => $phone);
            $mock->shouldReceive('verify')->once();
        });
        $data = [
            'name' => 'Invited Friend', 'username' => 'new-friend', 'phone' => '60123456789',
            'password' => 'password123', 'password_confirmation' => 'password123',
            'otp_code' => '123456', 'referral_code' => $referrer->referral_code,
        ];
        if (str_starts_with($route, '/api/')) $this->postJson($route, $data)->assertCreated();
        else $this->post($route, $data)->assertRedirect('/dashboard');

        $invitee = User::query()->where('username', 'new-friend')->firstOrFail();
        app(GoldenEggService::class)->registerInvitee($invitee);
        $this->assertDatabaseCount('golden_egg_entries', 1);
        $this->assertNull(GoldenEggEntry::query()->first()->qualified_at);
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($referrer));
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($invitee));

        $product = $this->product();
        $api = str_starts_with($route, '/api/');
        $purchaseUrl = ($api ? '/api/v1' : '').'/products/'.$product->id.'/purchase';
        if ($api) {
            Sanctum::actingAs($invitee);
            $this->postJson($purchaseUrl, ['wallet_type' => 'recharge'])->assertStatus(400);
        } else {
            $this->actingAs($invitee)->post($purchaseUrl, ['wallet_type' => 'recharge'])->assertSessionHas('error');
        }
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($referrer));
        $this->assertDatabaseCount('user_products', 0);
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($invitee));
        $invitee->wallets()->updateOrCreate(['type' => 'recharge'], ['balance' => 150, 'locked_balance' => 0]);
        for ($i = 0; $i < 2; $i++) {
            if ($api) $this->postJson($purchaseUrl, ['wallet_type' => 'recharge'])->assertCreated();
            else $this->post($purchaseUrl, ['wallet_type' => 'recharge'])->assertRedirect('/products')->assertSessionHas('success');
            $this->assertSame(1, app(GoldenEggService::class)->remainingChances($referrer));
            $this->assertSame($i + 1, app(GoldenEggService::class)->remainingChances($invitee));
        }
        $this->assertNotNull(GoldenEggEntry::query()->first()->qualified_at);
        $this->assertDatabaseCount('golden_egg_entries', 3);
    }

    public static function registrationRoutes(): array
    {
        return [['/register'], ['/api/v1/register']];
    }

    #[DataProvider('purchaseProducts')]
    public function test_each_product_purchase_earns_a_usable_chance_once_per_order(string $type, string $wallet): void
    {
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        $buyer = $this->user();
        $buyer->wallets()->create(['type' => $wallet, 'balance' => 150, 'locked_balance' => 0]);
        $product = $this->product();
        $product->update(['product_type' => $type]);
        $service = app(GoldenEggService::class);
        $purchase = app(ProductPurchaseService::class)->purchase($buyer, $product, $wallet);

        $service->givePurchaseChance($buyer, $purchase);
        $this->assertSame(1, $service->remainingChances($buyer));
        $this->assertDatabaseCount('golden_egg_entries', 1);
        $this->assertDatabaseHas('golden_egg_entries', [
            'user_id' => $buyer->id, 'user_product_id' => $purchase->id, 'invited_user_id' => null,
        ]);
        $this->onlyPrize();
        $draw = $service->draw($buyer, (string) Str::uuid(), 1);
        $this->assertSame(GoldenEggEntry::query()->sole()->id, $draw->entry_id);
        $this->assertSame(0, $service->remainingChances($buyer));

        $service->givePurchaseChance($buyer, $purchase);
        $this->assertSame(0, $service->remainingChances($buyer));
        app(ProductPurchaseService::class)->purchase($buyer, $product, $wallet);
        $this->assertSame(1, $service->remainingChances($buyer));
        $this->assertDatabaseCount('golden_egg_entries', 2);
    }

    public static function purchaseProducts(): array
    {
        return [
            [Product::TYPE_NORMAL, Wallet::TYPE_RECHARGE],
            [Product::TYPE_NORMAL, Wallet::TYPE_WITHDRAWABLE],
            [Product::TYPE_UPGRADE_DAY, Wallet::TYPE_RECHARGE],
            [Product::TYPE_LIMITED_TIME, Wallet::TYPE_RECHARGE],
            [Product::TYPE_INVITE_DAY, Wallet::TYPE_RECHARGE],
            [Product::TYPE_WEEKEND_SPECIAL, Wallet::TYPE_RECHARGE],
            [Product::TYPE_HOURLY, Wallet::TYPE_RECHARGE],
        ];
    }

    public function test_purchase_chances_only_apply_to_new_successful_orders_while_open_and_not_agents(): void
    {
        $buyer = $this->user();
        $buyer->wallets()->create(['type' => 'recharge', 'balance' => 150, 'locked_balance' => 0]);
        $product = $this->product();
        app(ProductPurchaseService::class)->purchase($buyer, $product);
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        $this->actingAs($buyer)->get('/golden-egg')->assertOk();
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($buyer));

        app(ProductPurchaseService::class)->purchase($buyer, $product);
        $this->assertSame(1, app(GoldenEggService::class)->remainingChances($buyer));
        Setting::setValue(GoldenEggService::SETTING_ENABLED, false);
        app(ProductPurchaseService::class)->purchase($buyer, $product);
        $this->assertSame(1, app(GoldenEggService::class)->remainingChances($buyer));

        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        $agent = $this->user(['is_virtual' => true]);
        $agent->wallets()->create(['type' => 'recharge', 'balance' => 50, 'locked_balance' => 0]);
        app(ProductPurchaseService::class)->purchase($agent, $product);
        $this->assertDatabaseCount('golden_egg_entries', 1);
        $this->assertNull(app(GoldenEggService::class)->remainingChances($agent));
    }

    public function test_reveal_catalog_includes_sold_out_rewards_but_the_draw_still_excludes_them(): void
    {
        $user = $this->user();
        $this->chance($user);
        $winner = $this->onlyPrize(['stock' => 1]);
        $soldOut = GoldenEggPrize::query()->where('slot', 2)->firstOrFail();
        $soldOut->update(['name' => 'Sold out display reward', 'type' => 'cash', 'amount' => 999, 'stock' => 0, 'weight' => 1000000]);

        $this->actingAs($user)->get('/golden-egg')->assertOk()
            ->assertSee('data-result-cell', false)
            ->assertSee(config('app.currency').' 999.00')
            ->assertDontSee(__('golden_egg.unavailable'));
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/golden-egg')->assertOk()
            ->assertJsonCount(9, 'data.prizes')
            ->assertJsonPath('data.prizes.1.id', $soldOut->id)
            ->assertJsonPath('data.prizes.1.amount', '999.00')
            ->assertJsonPath('data.prizes.1.available', false)
            ->assertJsonMissingPath('data.prizes.1.stock');
        $request = array_replace($this->drawRequest(), ['egg_number' => 5]);
        $this->postJson('/api/v1/golden-egg/draw', $request)->assertOk()
            ->assertJsonPath('data.prize.prize_id', $winner->id)
            ->assertJsonPath('data.prize.egg_number', 5);
        $this->postJson('/api/v1/golden-egg/draw', $request)->assertOk()
            ->assertJsonPath('data.prize.prize_id', $winner->id);
        $this->assertSame(0, $soldOut->fresh()->stock);
        $this->assertDatabaseCount('golden_egg_draws', 1);
    }

    public function test_closed_and_virtual_invitations_do_not_earn_chances_and_history_is_not_backfilled(): void
    {
        $user = $this->user();
        $oldInvitee = $this->user(['referrer_id' => $user->id]);
        app(GoldenEggService::class)->registerInvitee($oldInvitee);
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        $virtualInvitee = $this->user(['referrer_id' => $user->id, 'is_virtual' => true]);
        app(GoldenEggService::class)->registerInvitee($virtualInvitee);
        $product = $this->product();
        foreach ([$oldInvitee, $virtualInvitee] as $invitee) {
            $invitee->wallets()->create(['type' => 'recharge', 'balance' => 100, 'locked_balance' => 0]);
            app(ProductPurchaseService::class)->purchase($invitee, $product);
        }
        $this->actingAs($user)->get('/golden-egg')->assertOk();
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($user));
        $this->assertSame(1, app(GoldenEggService::class)->remainingChances($oldInvitee));
        $this->assertSame(0, GoldenEggEntry::query()->whereNotNull('invited_user_id')->count());
        $this->assertDatabaseCount('golden_egg_entries', 1);
    }

    public function test_first_purchase_while_closed_does_not_defer_reward_until_later_purchase(): void
    {
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        $referrer = $this->user();
        $invitee = $this->user(['referrer_id' => $referrer->id]);
        app(GoldenEggService::class)->registerInvitee($invitee);
        $invitee->wallets()->create(['type' => 'recharge', 'balance' => 100, 'locked_balance' => 0]);
        $product = $this->product();
        Setting::setValue(GoldenEggService::SETTING_ENABLED, false);
        app(ProductPurchaseService::class)->purchase($invitee, $product);
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        app(ProductPurchaseService::class)->purchase($invitee, $product);
        $this->assertSame(0, app(GoldenEggService::class)->remainingChances($referrer));
        $this->assertNull(GoldenEggEntry::query()->first()->qualified_at);
    }

    public function test_cash_reward_consumes_one_entry_and_stock_and_replay_never_awards_twice(): void
    {
        $user = $this->user();
        $this->chance($user);
        $prize = $this->onlyPrize(['amount' => 25, 'stock' => 1]);
        $request = $this->drawRequest();
        $this->actingAs($user)->postJson('/golden-egg/draw', $request)->assertOk()
            ->assertJsonPath('data.prize.amount', '25.00')->assertJsonPath('data.remaining_chances', 0);
        Setting::setValue(GoldenEggService::SETTING_ENABLED, false);
        $this->postJson('/golden-egg/draw', array_replace($request, ['egg_number' => 9]))->assertOk()
            ->assertJsonPath('data.prize.egg_number', 1);
        $this->assertSame(0, $prize->fresh()->stock);
        $this->assertEquals(25, $user->wallets()->where('type', Wallet::TYPE_RECHARGE)->value('balance'));
        $this->assertDatabaseCount('golden_egg_draws', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseHas('wallet_transactions', ['type' => 'golden_egg', 'wallet_type' => 'recharge']);
        $this->assertNotNull(GoldenEggEntry::query()->first()->used_at);
    }

    public function test_zero_stock_and_weight_are_excluded_and_last_unit_cannot_be_awarded_again(): void
    {
        $first = $this->user();
        $second = $this->user();
        $this->chance($first);
        $this->chance($second);
        $prize = $this->onlyPrize(['stock' => 1, 'weight' => 1]);
        GoldenEggPrize::query()->where('slot', 2)->update(['stock' => null, 'weight' => 0]);
        GoldenEggPrize::query()->where('slot', 3)->update(['stock' => 0, 'weight' => 1000000]);
        $draw = app(GoldenEggService::class)->draw($first, (string) Str::uuid(), 9);
        $this->assertSame($prize->id, $draw->prize_id);
        $this->actingAs($second)->postJson('/golden-egg/draw', $this->drawRequest())->assertStatus(400)
            ->assertJsonPath('message', __('golden_egg.sold_out'));
        $this->assertSame(1, app(GoldenEggService::class)->remainingChances($second));
        $this->assertDatabaseCount('golden_egg_draws', 1);
    }

    public function test_unlimited_stock_stays_unlimited_and_no_chance_cannot_be_bypassed(): void
    {
        $user = $this->user();
        $this->chance($user);
        $this->chance($user);
        $prize = $this->onlyPrize(['stock' => null]);
        $this->actingAs($user);
        $this->postJson('/golden-egg/draw', $this->drawRequest())->assertOk();
        $this->postJson('/golden-egg/draw', $this->drawRequest())->assertOk();
        $this->postJson('/golden-egg/draw', $this->drawRequest() + ['is_virtual' => true, 'remaining_chances' => 99])
            ->assertStatus(400)->assertJsonPath('message', __('golden_egg.no_chances'));
        $this->assertNull($prize->fresh()->stock);
        $this->assertDatabaseCount('golden_egg_draws', 2);
    }

    public function test_agent_receives_actual_cash_and_coupons_without_chances_or_stock_even_while_closed(): void
    {
        $agent = $this->user(['is_virtual' => true]);
        $prize = $this->onlyPrize(['stock' => 0, 'amount' => 50]);
        GoldenEggPrize::query()->update(['type' => 'cash', 'amount' => 50, 'weight' => 0, 'stock' => 0]);
        $this->actingAs($agent);
        for ($i = 0; $i < 3; $i++) $this->postJson('/golden-egg/draw', $this->drawRequest())->assertOk()
            ->assertJsonPath('data.remaining_chances', null);
        GoldenEggPrize::query()->update(['type' => 'coupon', 'discount_percent' => 15, 'amount' => 0]);
        $this->postJson('/golden-egg/draw', $this->drawRequest())->assertOk();
        $this->assertEquals(150, $agent->wallets()->where('type', 'recharge')->value('balance'));
        $this->assertDatabaseHas('purchase_coupons', ['user_id' => $agent->id, 'discount_percent' => 15]);
        $this->assertDatabaseCount('golden_egg_entries', 0);
        $this->assertDatabaseCount('golden_egg_draws', 4);
        $this->assertSame(0, $prize->fresh()->stock);
    }

    public function test_coupon_retries_do_not_duplicate_awards_and_api_history_is_private(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $this->chance($owner);
        $this->onlyPrize(['type' => 'coupon', 'amount' => 0, 'discount_percent' => 12.5]);
        $requestId = (string) Str::uuid();
        app(GoldenEggService::class)->draw($owner, $requestId, 3);
        app(GoldenEggService::class)->draw($owner, $requestId, 3);
        $this->assertDatabaseCount('purchase_coupons', 1);

        Sanctum::actingAs($other);
        $this->getJson('/api/v1/golden-egg')->assertOk()
            ->assertJsonCount(0, 'data.history')->assertJsonCount(0, 'data.coupons')
            ->assertJsonCount(9, 'data.prizes')->assertJsonMissingPath('data.prizes.0.weight');
        $this->postJson('/api/v1/golden-egg/draw', ['request_id' => $requestId, 'egg_number' => 10])
            ->assertUnprocessable();
        $this->postJson('/api/v1/golden-egg/draw', $this->drawRequest())
            ->assertStatus(400)->assertJsonPath('message', __('golden_egg.no_chances'));
        $this->assertDatabaseCount('purchase_coupons', 1);
    }

    public function test_failed_award_rolls_back_the_draw_and_preserves_entry_and_stock(): void
    {
        $user = $this->user();
        $this->chance($user);
        $prize = $this->onlyPrize(['amount' => 0, 'stock' => 1]);
        try {
            app(GoldenEggService::class)->draw($user, (string) Str::uuid(), 1);
            $this->fail('An invalid award should fail.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('golden_egg_draws', 0);
            $this->assertSame(1, app(GoldenEggService::class)->remainingChances($user));
            $this->assertSame(1, $prize->fresh()->stock);
        }
    }

    public function test_coupon_snapshot_is_used_once_on_the_discounted_price_without_changing_income(): void
    {
        $user = $this->user();
        $coupon = $this->coupon($user, 10);
        GoldenEggPrize::query()->update(['discount_percent' => 80]);
        $user->wallets()->create(['type' => 'recharge', 'balance' => 100, 'locked_balance' => 0]);
        $product = $this->product();
        $purchase = app(ProductPurchaseService::class)->purchase($user, $product, couponId: $coupon->id);
        $this->assertSame('45.00', $purchase->price_snapshot);
        $this->assertSame('2.00', $purchase->daily_income_snapshot);
        $this->assertEquals(50, $purchase->meta['original_price']);
        $this->assertEquals(55, $user->wallets()->where('type', 'recharge')->value('balance'));
        $this->assertSame($purchase->id, $coupon->fresh()->user_product_id);
        $this->assertNotNull($coupon->fresh()->used_at);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('golden_egg.invalid_coupon'));
        app(ProductPurchaseService::class)->purchase($user, $product, couponId: $coupon->id);
    }

    public function test_other_users_coupon_is_rejected_and_failed_payment_does_not_consume_coupon(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $coupon = $this->coupon($owner, 20);
        $product = $this->product();
        foreach ([$other, $owner] as $buyer) {
            try {
                app(ProductPurchaseService::class)->purchase($buyer, $product, couponId: $coupon->id);
                $this->fail('The purchase should fail.');
            } catch (RuntimeException) {
                $this->assertNull($coupon->fresh()->used_at);
                $this->assertDatabaseCount('user_products', 0);
                $this->assertDatabaseCount('wallet_transactions', 0);
                $this->assertSame(0, app(GoldenEggService::class)->remainingChances($buyer));
            }
        }
    }

    public function test_api_checkout_exposes_coupons_rejects_stacking_and_deducts_the_discounted_total(): void
    {
        $user = $this->user();
        $coupon = $this->coupon($user, 10);
        $secondCoupon = $this->coupon($user, 20);
        $user->wallets()->create(['type' => 'withdrawable', 'balance' => 46, 'locked_balance' => 0]);
        $product = $this->product();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(2, 'data.coupons');
        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'wallet_type' => 'withdrawable', 'coupon_id' => [$coupon->id, $secondCoupon->id],
        ])->assertUnprocessable();
        $this->postJson('/api/v1/products/'.$product->id.'/purchase', [
            'wallet_type' => 'withdrawable', 'coupon_id' => $coupon->id,
        ])->assertCreated();
        $this->assertEquals(1, $user->wallets()->where('type', 'withdrawable')->value('balance'));
        $this->assertNull($secondCoupon->fresh()->used_at);
        $this->assertDatabaseHas('wallet_transactions', ['amount' => 45, 'type' => 'product_purchase']);
    }

    public function test_coupon_rounding_and_full_discount_work_without_a_positive_wallet_debit(): void
    {
        $user = $this->user();
        $coupon = $this->coupon($user, 10);
        $this->assertSame(9.05, $coupon->discountedPrice('10.05'));
        $free = $this->coupon($user, 100);
        $purchase = app(ProductPurchaseService::class)->purchase($user, $this->product(), couponId: $free->id);
        $this->assertSame('0.00', $purchase->price_snapshot);
        $this->assertNotNull($free->fresh()->used_at);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, app(GoldenEggService::class)->remainingChances($user));
    }

    public function test_dashboard_and_prize_page_share_the_same_activity_switch(): void
    {
        $admin = AdminUser::query()->create(['username' => 'egg-dashboard-admin', 'password' => 'password', 'status' => 'active']);
        foreach (['ViewAny:GoldenEggPrize', 'Manage:GoldenEgg'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $dashboard = Livewire::test(Dashboard::class)
            ->assertActionVisible('goldenEggSettings')
            ->assertActionExists('maintenanceNoticeSettings')
            ->assertActionExists('welcomeBonusSettings')
            ->assertActionExists('pointsSettings')
            ->callAction('goldenEggSettings', ['enabled' => true])
            ->assertHasNoActionErrors();
        $this->assertTrue(Setting::bool(GoldenEggService::SETTING_ENABLED));

        Livewire::test(ManageGoldenEggPrizes::class)
            ->mountAction('activity_settings')
            ->assertActionDataSet(['enabled' => true])
            ->setActionData(['enabled' => false])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        $this->assertFalse(Setting::bool(GoldenEggService::SETTING_ENABLED));
        $dashboard->mountAction('goldenEggSettings')->assertActionDataSet(['enabled' => false]);
    }

    public function test_dashboard_activity_switch_requires_manage_permission(): void
    {
        $admin = AdminUser::query()->create(['username' => 'egg-viewer', 'password' => 'password', 'status' => 'active']);
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Dashboard::class)
            ->assertActionHidden('goldenEggSettings')
            ->call('mountAction', 'goldenEggSettings')
            ->assertActionNotMounted('goldenEggSettings');
        $this->assertFalse(Setting::bool(GoldenEggService::SETTING_ENABLED));
    }

    public function test_admin_can_edit_nine_prizes_switch_unlimited_stock_and_enable_the_activity(): void
    {
        $admin = AdminUser::query()->create(['username' => 'egg-admin', 'password' => 'password', 'status' => 'active']);
        foreach (['ViewAny:GoldenEggPrize', 'View:GoldenEggPrize', 'Update:GoldenEggPrize', 'Manage:GoldenEgg'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $prize = GoldenEggPrize::query()->first();
        $manager = Livewire::test(ManageGoldenEggPrizes::class)->assertCanSeeTableRecords(GoldenEggPrize::all());
        $manager->callAction(TestAction::make('edit')->table($prize), [
            'name' => 'A golden discount', 'type' => 'coupon', 'discount_percent' => 12.5,
            'weight' => 25, 'unlimited_stock' => true,
        ])->assertHasNoActionErrors();
        $this->assertNull($prize->fresh()->stock);
        $this->assertSame('12.50', $prize->fresh()->discount_percent);
        $this->assertSame('0.00', $prize->fresh()->amount);
        $manager->callAction(TestAction::make('edit')->table($prize), [
            'unlimited_stock' => false, 'stock' => 8, 'weight' => 0,
        ])->assertHasNoActionErrors();
        $this->assertSame(8, $prize->fresh()->stock);
        $this->assertSame(0, $prize->fresh()->weight);
        $manager->callAction('activity_settings', ['enabled' => true])->assertHasNoActionErrors();
        $this->assertTrue(Setting::bool(GoldenEggService::SETTING_ENABLED));
        $this->assertDatabaseCount('golden_egg_prizes', 9);
    }

    public function test_admin_validation_rejects_invalid_weights_stock_and_discount(): void
    {
        $admin = AdminUser::query()->create(['username' => 'egg-admin', 'password' => 'password', 'status' => 'active']);
        foreach (['ViewAny:GoldenEggPrize', 'Update:GoldenEggPrize'] as $permission) {
            $admin->givePermissionTo(Permission::findOrCreate($permission, 'admin'));
        }
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ManageGoldenEggPrizes::class)
            ->assertActionHidden('activity_settings')
            ->callAction(TestAction::make('edit')->table(GoldenEggPrize::query()->first()), [
                'type' => 'coupon', 'discount_percent' => 101, 'weight' => -1,
                'unlimited_stock' => false, 'stock' => -1,
            ])->assertHasActionErrors(['discount_percent', 'weight', 'stock']);
    }

    private function user(array $attributes = []): User
    {
        $number = User::query()->count() + 1;

        return User::query()->create(array_replace([
            'name' => 'Egg User '.$number, 'username' => 'egg-user-'.$number,
            'password' => 'password', 'referral_code' => 'EGG'.$number, 'status' => 'active',
            'order_status' => true, 'is_virtual' => false, 'accumulated_recharge' => 0,
        ], $attributes));
    }

    private function chance(User $user): void
    {
        Setting::setValue(GoldenEggService::SETTING_ENABLED, true);
        GoldenEggEntry::query()->create([
            'user_id' => $user->id, 'invited_user_id' => $this->user(['referrer_id' => $user->id])->id,
            'qualified_at' => now(),
        ]);
    }

    private function onlyPrize(array $attributes = []): GoldenEggPrize
    {
        GoldenEggPrize::query()->update(['weight' => 0]);
        $prize = GoldenEggPrize::query()->firstOrFail();
        $prize->update(array_replace(['type' => 'cash', 'amount' => 10, 'discount_percent' => 0, 'weight' => 1, 'stock' => null], $attributes));

        return $prize;
    }

    private function coupon(User $user, float $percent): PurchaseCoupon
    {
        $this->onlyPrize(['type' => 'coupon', 'amount' => 0, 'discount_percent' => $percent]);
        $this->chance($user);
        $draw = app(GoldenEggService::class)->draw($user, (string) Str::uuid(), 1);

        return PurchaseCoupon::query()->where('golden_egg_draw_id', $draw->id)->firstOrFail();
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Golden Product', 'code' => 'EGG', 'price' => 50, 'daily_income' => 2,
            'duration_days' => 10, 'max_purchase_per_user' => 10, 'status' => 'active',
        ]);
    }

    private function drawRequest(): array
    {
        return ['request_id' => (string) Str::uuid(), 'egg_number' => 1];
    }
}
