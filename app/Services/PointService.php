<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PointService
{
    public const SETTING_POINTS_ENABLED = 'points_enabled';

    public const SETTING_UPGRADE_DAY_ENABLED = 'upgrade_day_points_enabled';
    public const SETTING_UPGRADE_DAY_AMOUNT = 'upgrade_day_points_amount';

    public const SETTING_INVITE_DAY_ENABLED = 'invite_day_points_enabled';
    public const SETTING_INVITE_DAY_AMOUNT = 'invite_day_points_amount';

    public const SETTING_REDEEM_ENABLED = 'points_cash_exchange_enabled';
    public const SETTING_POINTS_TO_CASH_RATE = 'points_to_cash_rate';
    public const SETTING_MINIMUM_REDEEM_POINTS = 'minimum_points_to_redeem';

    public const SETTING_UPGRADE_DAY_WEEKDAYS = 'upgrade_day_points_weekdays';
    public const SETTING_INVITE_DAY_WEEKDAYS = 'invite_day_points_weekdays';

    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function pointsEnabled(): bool
    {
        return Setting::bool(self::SETTING_POINTS_ENABLED, false);
    }

    public function giveUpgradeDayPurchasePoints(User $user, UserProduct $userProduct): void
    {
        if (!$this->pointsEnabled()) {
            return;
        }

        if (!Setting::bool(self::SETTING_UPGRADE_DAY_ENABLED, false)) {
            return;
        }

        $upgradeWeekdays = $this->settingWeekdays(
            self::SETTING_UPGRADE_DAY_WEEKDAYS,
            [1, 2]
        );

        if (!in_array((int) now()->isoWeekday(), $upgradeWeekdays, true)) {
            return;
        }

        $settingPoints = (float) Setting::decimal(self::SETTING_UPGRADE_DAY_AMOUNT, 0);
        $productExtraPoints = (float) ($userProduct->extra_points_snapshot ?? 0);

        $points = $productExtraPoints > 0 ? $productExtraPoints : $settingPoints;

        if ($points <= 0) {
            return;
        }

        $alreadyGiven = PointTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', PointTransaction::TYPE_UPGRADE_DAY_PURCHASE)
            ->where('reference_type', $userProduct::class)
            ->where('reference_id', $userProduct->id)
            ->exists();

        if ($alreadyGiven) {
            return;
        }

        $this->credit(
            user: $user,
            type: PointTransaction::TYPE_UPGRADE_DAY_PURCHASE,
            points: $points,
            reference: $userProduct,
            remarks: 'Upgrade day product purchase points',
            meta: [
                'user_product_id' => $userProduct->id,
                'product_id' => $userProduct->product_id,
            ],
        );
    }

    public function giveInviteDayRegisterPoints(User $referrer, User $newUser): void
    {
        if (!$this->pointsEnabled()) {
            return;
        }

        if (!Setting::bool(self::SETTING_INVITE_DAY_ENABLED, false)) {
            return;
        }

        $inviteWeekdays = $this->settingWeekdays(
            self::SETTING_INVITE_DAY_WEEKDAYS,
            [4, 5]
        );

        if (!in_array((int) now()->isoWeekday(), $inviteWeekdays, true)) {
            return;
        }

        $points = (float) Setting::decimal(self::SETTING_INVITE_DAY_AMOUNT, 0);

        if ($points <= 0) {
            return;
        }

        $alreadyGiven = PointTransaction::query()
            ->where('user_id', $referrer->id)
            ->where('type', PointTransaction::TYPE_INVITE_DAY_REGISTER)
            ->where('reference_type', $newUser::class)
            ->where('reference_id', $newUser->id)
            ->exists();

        if ($alreadyGiven) {
            return;
        }

        $this->credit(
            user: $referrer,
            type: PointTransaction::TYPE_INVITE_DAY_REGISTER,
            points: $points,
            reference: $newUser,
            remarks: 'Invite day new registration points',
            meta: [
                'new_user_id' => $newUser->id,
                'new_username' => $newUser->username,
            ],
        );
    }

    public function redeemToWithdrawableWallet(User $user, float $points): void
    {
        if (!$this->pointsEnabled()) {
            throw new RuntimeException(__('points.points_disabled'));
        }

        if (!Setting::bool(self::SETTING_REDEEM_ENABLED, false)) {
            throw new RuntimeException(__('points.redeem_disabled'));
        }

        $minimumPoints = (float) Setting::decimal(self::SETTING_MINIMUM_REDEEM_POINTS, 0);

        if ($points < $minimumPoints) {
            throw new RuntimeException(__('points.minimum_points_required', [
                'points' => number_format($minimumPoints, 2),
            ]));
        }

        $rate = (float) Setting::decimal(self::SETTING_POINTS_TO_CASH_RATE, 100);

        if ($rate <= 0) {
            throw new RuntimeException(__('points.invalid_exchange_rate'));
        }

        $cashAmount = round($points / $rate, 2);

        if ($cashAmount <= 0) {
            throw new RuntimeException(__('points.invalid_redeem_amount'));
        }

        DB::transaction(function () use ($user, $points, $cashAmount): void {
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((float) $user->points_balance < $points) {
                throw new RuntimeException(__('points.insufficient_points'));
            }

            $this->debit(
                user: $user,
                type: PointTransaction::TYPE_REDEEM_CASH,
                points: $points,
                reference: null,
                remarks: 'Redeem points to cash',
                meta: [
                    'cash_amount' => $cashAmount,
                ],
            );

            $this->walletService->credit(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'points_redeem',
                amount: $cashAmount,
                reference: null,
                remarks: 'Points redeemed to withdrawable wallet',
                meta: [
                    'points' => $points,
                    'cash_amount' => $cashAmount,
                ],
            );
        });
    }

    public function credit(
        User $user,
        string $type,
        float $points,
        ?Model $reference = null,
        ?string $remarks = null,
        array $meta = [],
    ): PointTransaction {
        if ($points <= 0) {
            throw new RuntimeException('Points must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $type, $points, $reference, $remarks, $meta): PointTransaction {
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (float) $user->points_balance;
            $after = round($before + $points, 2);

            $user->forceFill([
                'points_balance' => $after,
            ])->save();

            return PointTransaction::query()->create([
                'user_id' => $user->id,
                'direction' => PointTransaction::DIRECTION_CREDIT,
                'type' => $type,
                'points' => $points,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'remarks' => $remarks,
                'meta' => $meta ?: null,
            ]);
        });
    }

    public function debit(
        User $user,
        string $type,
        float $points,
        ?Model $reference = null,
        ?string $remarks = null,
        array $meta = [],
    ): PointTransaction {
        if ($points <= 0) {
            throw new RuntimeException('Points must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $type, $points, $reference, $remarks, $meta): PointTransaction {
            $user = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (float) $user->points_balance;

            if ($before < $points) {
                throw new RuntimeException(__('points.insufficient_points'));
            }

            $after = round($before - $points, 2);

            $user->forceFill([
                'points_balance' => $after,
            ])->save();

            return PointTransaction::query()->create([
                'user_id' => $user->id,
                'direction' => PointTransaction::DIRECTION_DEBIT,
                'type' => $type,
                'points' => $points,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $reference ? $reference::class : null,
                'reference_id' => $reference?->getKey(),
                'remarks' => $remarks,
                'meta' => $meta ?: null,
            ]);
        });
    }

    private function settingWeekdays(string $key, array $default): array
    {
        $value = Setting::getValue($key);

        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);

        if (!is_array($decoded)) {
            return $default;
        }

        return collect($decoded)
            ->map(fn($day) => (int) $day)
            ->filter(fn($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();
    }
}