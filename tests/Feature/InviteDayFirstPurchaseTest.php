<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RechargeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\InviteDayBonusService;
use App\Services\PointService;
use App\Services\ProductPurchaseService;
use App\Services\RechargeApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class InviteDayFirstPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is unavailable.');
        }

        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'TIMEZONE' => 'Asia/Kuala_Lumpur'] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));

        foreach ([
            PointService::SETTING_POINTS_ENABLED => true,
            PointService::SETTING_INVITE_DAY_ENABLED => true,
            PointService::SETTING_INVITE_DAY_WEEKDAYS => '[4,5]',
            PointService::SETTING_INVITE_DAY_AMOUNT => 20,
            PointService::SETTING_UPGRADE_DAY_ENABLED => false,
            PointService::SETTING_UPGRADE_DAY_AMOUNT => 10,
        ] as $key => $value) {
            Setting::setValue($key, $value);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('purchaseRules')]
    public function test_preserved_first_purchase_reward_rules(string $registeredAt, string $purchasedAt, int $extraPoints, array $settings, float $expected): void
    {
        foreach ($settings as $key => $value) {
            Setting::setValue($key, $value);
        }

        [$referrer, $user] = $this->invitedUser($registeredAt);
        Carbon::setTestNow(Carbon::parse($purchasedAt));

        $purchase = app(ProductPurchaseService::class)->purchase($user, $this->product($extraPoints));
        // Exercise the retained calculation directly; purchases no longer call it.
        app(PointService::class)->giveInviteDayFirstPurchasePoints($user, $purchase);

        $this->assertEquals($expected, $referrer->fresh()->points_balance);
        $this->assertEquals(0, $user->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', $expected > 0 ? 1 : 0);
    }

    public static function purchaseRules(): array
    {
        return [
            'Thursday default points with upgrade activity disabled' => ['2026-09-17 09:00', '2026-09-17 10:00', 0, [], 10],
            'Friday product override does not add default' => ['2026-09-18 09:00', '2026-09-18 23:59:59', 30, [], 30],
            'smaller product override still wins' => ['2026-09-17 09:00', '2026-09-17 10:00', 3, [], 3],
            'Thursday registration Monday purchase' => ['2026-09-17 09:00', '2026-09-21 10:00', 30, [], 0],
            'Monday registration Thursday purchase' => ['2026-09-14 09:00', '2026-09-17 10:00', 30, [], 0],
            'crossing midnight between invitation days' => ['2026-09-17 23:59:59', '2026-09-18 00:00:00', 30, [], 0],
            'next week is ineligible' => ['2026-09-17 09:00', '2026-09-24 10:00', 30, [], 0],
            'same day outside invitation weekdays' => ['2026-09-14 09:00', '2026-09-14 10:00', 30, [], 0],
            'configured weekdays apply' => ['2026-09-14 09:00', '2026-09-14 10:00', 0, [PointService::SETTING_INVITE_DAY_WEEKDAYS => '[1]'], 10],
            'points disabled' => ['2026-09-17 09:00', '2026-09-17 10:00', 30, [PointService::SETTING_POINTS_ENABLED => false], 0],
            'invitation activity disabled' => ['2026-09-17 09:00', '2026-09-17 10:00', 30, [PointService::SETTING_INVITE_DAY_ENABLED => false], 0],
            'no positive reward' => ['2026-09-17 09:00', '2026-09-17 10:00', 0, [PointService::SETTING_UPGRADE_DAY_AMOUNT => 0], 0],
            'product reward with zero default' => ['2026-09-17 09:00', '2026-09-17 10:00', 30, [PointService::SETTING_UPGRADE_DAY_AMOUNT => 0], 30],
        ];
    }

    #[DataProvider('firstDepositRules')]
    public function test_first_approved_deposit_requirement(array $deposits, float $expected): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        Setting::setValue(PointService::SETTING_INVITE_DAY_AMOUNT, 0);
        [$referrer, $user] = $this->invitedUser();
        $approvals = [];

        foreach ($deposits as [$amount, $status, $reviewedAt]) {
            $deposit = $this->deposit($user, $amount, $status === 'approved' ? 'pending' : $status);

            if ($status === 'approved') {
                $approvals[] = [$deposit, $reviewedAt];
            }
        }

        usort($approvals, fn (array $a, array $b): int => strcmp($a[1], $b[1]));

        foreach ($approvals as [$deposit, $reviewedAt]) {
            Carbon::setTestNow(Carbon::parse($reviewedAt));
            app(RechargeApprovalService::class)->approve($deposit, null);
        }

        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));
        $purchase = app(ProductPurchaseService::class)->purchase($user, $this->product(), Wallet::TYPE_WITHDRAWABLE);
        app(PointService::class)->giveInviteDayFirstPurchasePoints($user, $purchase);

        $this->assertEquals($expected, $referrer->fresh()->points_balance);
    }

    public static function firstDepositRules(): array
    {
        return [
            'no approved deposit' => [[], 0],
            'pending deposit' => [[[100, 'pending', null]], 0],
            'rejected deposit' => [[[100, 'rejected', null]], 0],
            'exact minimum qualifies' => [[[100, 'approved', '2026-09-17 09:30']], 10],
            'below minimum' => [[[99, 'approved', '2026-09-17 09:30']], 0],
            'later top-up cannot fix low first deposit' => [[[50, 'approved', '2026-09-17 09:30'], [100, 'approved', '2026-09-17 09:40']], 0],
            'pending request does not count as first deposit' => [[[50, 'pending', null], [100, 'approved', '2026-09-17 09:40']], 10],
            'approval order rather than submission order' => [[[50, 'approved', '2026-09-17 09:40'], [100, 'approved', '2026-09-17 09:30']], 10],
        ];
    }

    public function test_first_deposit_uses_actual_approval_order_when_timestamps_match(): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        Setting::setValue(PointService::SETTING_INVITE_DAY_AMOUNT, 0);
        [$referrer, $user] = $this->invitedUser();
        $laterApproval = $this->deposit($user, 100, 'pending');
        $firstApproval = $this->deposit($user, 50, 'pending');

        app(RechargeApprovalService::class)->approve($firstApproval, null);
        app(RechargeApprovalService::class)->approve($laterApproval, null);
        $purchase = app(ProductPurchaseService::class)->purchase($user, $this->product());
        app(PointService::class)->giveInviteDayFirstPurchasePoints($user, $purchase);

        $this->assertEquals(0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_web_and_api_purchases_do_not_add_first_purchase_points_to_existing_invitation_points(): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        [$referrer, $user] = $this->invitedUser();
        app(RechargeApprovalService::class)->approve($this->deposit($user, 100, 'pending'), null);
        $this->assertEquals(20, $referrer->fresh()->points_balance);

        Sanctum::actingAs($user);
        $product = $this->product(30);
        $this->postJson("/api/v1/products/{$product->id}/purchase", ['wallet_type' => Wallet::TYPE_RECHARGE])->assertCreated();

        $this->actingAs($user, 'web')
            ->post(route('products.purchase', $this->product(80)), ['wallet_type' => Wallet::TYPE_WITHDRAWABLE])
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');

        $this->assertEquals(20, $referrer->fresh()->points_balance);
        $this->assertDatabaseMissing('point_transactions', [
            'user_id' => $referrer->id,
            'type' => 'invite_day_first_purchase',
        ]);
        $this->assertDatabaseCount('point_transactions', 1);

        Sanctum::actingAs($referrer);
        $this->getJson('/api/v1/points')->assertOk()->assertJsonMissing(['type' => 'invite_day_first_purchase']);

        $this->withoutVite();
        $this->actingAs($referrer->fresh(), 'web')->withSession(['locale' => 'en'])->get('/points')
            ->assertOk()
            ->assertDontSee('Invite Day First Purchase Reward')
            ->assertDontSee('When an invited member registers and makes their first product purchase on the same invite day');
    }

    public function test_ineligible_first_purchase_cannot_be_replaced_by_a_later_product_or_deleted_order(): void
    {
        [$referrer, $user] = $this->invitedUser();
        Setting::setValue(PointService::SETTING_INVITE_DAY_ENABLED, false);
        $firstPurchase = app(ProductPurchaseService::class)->purchase($user, $this->product(30));
        $firstPurchase->delete();

        Setting::setValue(PointService::SETTING_INVITE_DAY_ENABLED, true);
        app(ProductPurchaseService::class)->purchase($user, $this->product(50));

        $this->assertEquals(0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_purchase_before_qualifying_first_deposit_is_not_rewarded_later(): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        [$referrer, $user] = $this->invitedUser();
        app(ProductPurchaseService::class)->purchase($user, $this->product(30), Wallet::TYPE_WITHDRAWABLE);

        app(RechargeApprovalService::class)->approve($this->deposit($user, 100, 'pending'), null);
        app(ProductPurchaseService::class)->purchase($user, $this->product(50));

        $this->assertEquals(20, $referrer->fresh()->points_balance);
        $this->assertDatabaseMissing('point_transactions', ['type' => 'invite_day_first_purchase']);
    }

    public function test_cross_day_manual_deposit_cannot_be_rewarded_by_a_later_recharge_or_purchase(): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        [$referrer, $user] = $this->invitedUser('2026-09-11 22:19:54');
        $this->travelTo(Carbon::parse('2026-09-14 20:48:43'));

        app(RechargeApprovalService::class)->approve($this->deposit($user, 100, 'pending'), null);
        $this->assertEquals(0, $referrer->fresh()->points_balance);

        $this->travelTo(Carbon::parse('2026-09-17 10:00:00'));
        app(RechargeApprovalService::class)->approve($this->deposit($user, 200, 'pending'), null);
        app(ProductPurchaseService::class)->purchase($user, $this->product(30));

        $this->assertEquals(0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    #[DataProvider('sameDayRechargeRules')]
    public function test_required_first_recharge_must_be_approved_on_the_registration_invite_day(
        string $registeredAt,
        string $approvedAt,
        bool $eligible,
    ): void {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        [$referrer, $user] = $this->invitedUser($registeredAt);

        $this->assertFalse(app(InviteDayBonusService::class)->shouldGiveOnRegistration());

        Carbon::setTestNow(Carbon::parse($approvedAt));
        $recharge = app(RechargeApprovalService::class)->approve($this->deposit($user, 100, 'pending'), null);
        app(InviteDayBonusService::class)->handleApprovedRecharge($recharge);

        $this->assertEquals($eligible ? 20 : 0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', $eligible ? 1 : 0);

        app(ProductPurchaseService::class)->purchase($user, $this->product(30));

        $this->assertEquals($eligible ? 20 : 0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', $eligible ? 1 : 0);
    }

    public static function sameDayRechargeRules(): array
    {
        return [
            'same invite day qualifies' => ['2026-09-17 09:00:00', '2026-09-17 10:00:00', true],
            'crossing midnight does not qualify' => ['2026-09-17 23:59:59', '2026-09-18 00:00:00', false],
            'next invite day in the same week does not qualify' => ['2026-09-17 09:00:00', '2026-09-18 10:00:00', false],
            'same date outside invite days does not qualify' => ['2026-09-14 09:00:00', '2026-09-14 10:00:00', false],
        ];
    }

    public function test_registration_reward_cannot_qualify_after_a_low_first_deposit(): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, true);
        Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 100);
        [$referrer, $user] = $this->invitedUser();

        app(RechargeApprovalService::class)->approve($this->deposit($user, 99, 'pending'), null);
        app(RechargeApprovalService::class)->approve($this->deposit($user, 100, 'pending'), null);

        $this->assertEquals(0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_registration_reward_still_applies_without_a_deposit_when_requirement_is_disabled(): void
    {
        Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, false);
        [$referrer, $user] = $this->invitedUser();

        $this->assertTrue(app(InviteDayBonusService::class)->shouldGiveOnRegistration());
        app(PointService::class)->giveInviteDayRegisterPoints($referrer, $user);
        app(PointService::class)->giveInviteDayRegisterPoints($referrer, $user);

        $this->assertEquals(20, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 1);
    }

    public function test_failed_purchase_can_be_retried_without_awarding_referrer_points(): void
    {
        [$referrer, $user] = $this->invitedUser();
        $wallet = $user->wallets()->where('type', Wallet::TYPE_RECHARGE)->firstOrFail();
        $wallet->update(['balance' => 0]);
        $product = $this->product(30);

        try {
            app(ProductPurchaseService::class)->purchase($user, $product);
            $this->fail('An unfunded purchase should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Insufficient wallet balance.', $exception->getMessage());
        }

        $this->assertDatabaseCount('user_products', 0);
        $this->assertDatabaseCount('point_transactions', 0);
        $wallet->update(['balance' => 100]);
        app(ProductPurchaseService::class)->purchase($user, $product);
        $this->assertEquals(0, $referrer->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 0);
    }

    public function test_purchase_still_awards_buyer_upgrade_points_without_referrer_reward(): void
    {
        Setting::setValue(PointService::SETTING_UPGRADE_DAY_ENABLED, true);
        Setting::setValue(PointService::SETTING_UPGRADE_DAY_WEEKDAYS, '[4]');
        [$referrer, $user] = $this->invitedUser();
        $ancestor = $this->user();
        $referrer->update(['referrer_id' => $ancestor->id]);

        app(ProductPurchaseService::class)->purchase($user, $this->product(30));

        $this->assertEquals(30, $user->fresh()->points_balance);
        $this->assertEquals(0, $referrer->fresh()->points_balance);
        $this->assertEquals(0, $ancestor->fresh()->points_balance);
        $this->assertDatabaseCount('point_transactions', 1);
    }

    private function invitedUser(string $registeredAt = '2026-09-17 09:00:00'): array
    {
        $referrer = $this->user();
        $user = $this->user($referrer);
        $user->forceFill(['created_at' => Carbon::parse($registeredAt)])->saveQuietly();

        foreach ([Wallet::TYPE_RECHARGE, Wallet::TYPE_WITHDRAWABLE] as $type) {
            Wallet::query()->create(['user_id' => $user->id, 'type' => $type, 'balance' => 1000, 'locked_balance' => 0]);
        }

        return [$referrer, $user];
    }

    private function user(?User $referrer = null): User
    {
        return User::query()->create([
            'name' => 'Invite User '.++$this->sequence,
            'username' => 'invite_user_'.$this->sequence,
            'password' => 'password',
            'status' => 'active',
            'order_status' => true,
            'referrer_id' => $referrer?->id,
        ]);
    }

    private function product(int $extraPoints = 0): Product
    {
        return Product::query()->create([
            'name' => 'Invite Product '.++$this->sequence,
            'code' => 'INVITE-'.$this->sequence,
            'price' => 50,
            'daily_income' => 1,
            'duration_days' => 2,
            'status' => 'active',
            'extra_points' => $extraPoints,
        ]);
    }

    private function deposit(User $user, float $amount, string $status): RechargeRequest
    {
        return RechargeRequest::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => $status,
        ]);
    }
}
