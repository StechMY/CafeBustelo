<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\Setting;
use App\Models\TreasureChestClaim;
use App\Models\TreasureKeyEntry;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Services\Settings\TreasureChestSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TreasureChestService
{
    public const SETTING_ENABLED = 'treasure_chest_enabled';
    public const SETTING_START = 'treasure_chest_starts_at';
    public const SETTING_END = 'treasure_chest_ends_at';

    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function isActiveAt(?CarbonInterface $time = null): bool
    {
        return $this->activityStatus($time) === 'active';
    }

    private function activityStatus(?CarbonInterface $time = null): string
    {
        if (! Setting::bool(self::SETTING_ENABLED)) {
            return 'closed';
        }
        $time ??= now();
        $start = Setting::getValue(self::SETTING_START);
        $end = Setting::getValue(self::SETTING_END);

        if ($start && $time->lt(Carbon::parse($start, config('app.timezone')))) {
            return 'scheduled';
        }
        if ($end && $time->gte(Carbon::parse($end, config('app.timezone')))) {
            return 'ended';
        }

        return 'active';
    }

    public function givePurchaseKeys(User $buyer, UserProduct $purchase): void
    {
        if (! $buyer->referrer_id || ! $this->isActiveAt($purchase->purchased_at)) {
            return;
        }
        $code = strtoupper(str_replace('-', '', $purchase->meta['product_code_snapshot'] ?? ''));
        $keys = 0;
        $products = app(TreasureChestSettings::class)->rules()['products'];
        // An explicitly configured product takes precedence over a weekend price fallback.
        foreach ($products as $product) {
            if ($code === strtoupper(str_replace('-', '', $product['code']))) {
                $keys = $product['keys'];
                break;
            }
        }
        foreach ($keys === 0 ? $products : [] as $product) {
            // Weekend products may have custom production codes; match their original price too.
            $weekendMatch = $purchase->product_type_snapshot === Product::TYPE_WEEKEND_SPECIAL
                && $product['match_weekend_price']
                && (float) ($purchase->meta['original_price'] ?? 0) === (float) $product['price'];
            if ($weekendMatch) {
                $keys = $product['keys'];
                break;
            }
        }
        if ($keys === 0) {
            return;
        }

        DB::transaction(function () use ($buyer, $purchase, $keys): void {
            $referrer = User::query()->whereKey($buyer->referrer_id)->lockForUpdate()->first();
            if (! $referrer || $referrer->id === $buyer->id) {
                return;
            }
            TreasureKeyEntry::query()->firstOrCreate(['user_product_id' => $purchase->id], [
                'user_id' => $referrer->id, 'invited_user_id' => $buyer->id, 'keys' => $keys,
            ]);
        }, 3);
    }

    public function totalKeys(User $user): int
    {
        return (int) TreasureKeyEntry::query()->where('user_id', $user->id)->sum('keys')
            + (int) TreasureChestClaim::query()->where('user_id', $user->id)->sum('keys');
    }

    public function claim(User $user, int $number): TreasureChestClaim
    {
        $prize = app(TreasureChestSettings::class)->rules()['prizes'][$number] ?? null;
        if (! $prize) {
            throw new RuntimeException(__('treasure_chest.invalid_chest'));
        }

        return DB::transaction(function () use ($user, $number, $prize): TreasureChestClaim {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = TreasureChestClaim::query()->where('user_id', $user->id)->where('chest_number', $number)->first();
            if ($existing) {
                return $existing;
            }
            $status = $this->activityStatus();
            if ($status !== 'active') {
                throw new RuntimeException(__('treasure_chest.'.$status));
            }
            if ($this->totalKeys($user) < $prize['threshold']) {
                throw new RuntimeException(__('treasure_chest.not_enough_keys'));
            }
            $claim = TreasureChestClaim::query()->create([
                'user_id' => $user->id, 'chest_number' => $number, ...$prize,
            ]);
            if ($claim->amount > 0) {
                $this->walletService->credit(
                    user: $user, walletType: Wallet::TYPE_WITHDRAWABLE, transactionType: 'treasure_chest',
                    amount: $claim->amount, reference: $claim, remarks: 'Treasure chest '.$number,
                );
            }
            if ($claim->discount_percent > 0) {
                PurchaseCoupon::query()->create([
                    'user_id' => $user->id, 'treasure_chest_claim_id' => $claim->id,
                    'discount_percent' => $claim->discount_percent,
                ]);
            }

            return $claim;
        }, 3);
    }

    public function activity(User $user): array
    {
        $claims = TreasureChestClaim::query()->where('user_id', $user->id)->orderBy('chest_number')->get();
        $keys = $this->totalKeys($user);
        $status = $this->activityStatus();
        $enabled = $status === 'active';
        $chests = [];
        $rules = app(TreasureChestSettings::class)->rules();
        foreach ($rules['prizes'] as $number => $prize) {
            $claim = $claims->firstWhere('chest_number', $number);
            if ($claim) {
                $prize = $claim->only(['threshold', 'amount', 'keys', 'discount_percent']);
            }
            $chests[] = [
                'number' => $number, ...$prize, 'claimed' => $claim !== null,
                'claimable' => $enabled && ! $claim && $keys >= $prize['threshold'],
                'claimed_at' => $claim?->created_at->toIso8601String(),
            ];
        }

        return [
            'enabled' => $enabled, 'total_keys' => $keys, 'chests' => $chests,
            'status' => $status, 'status_message' => $enabled ? null : __('treasure_chest.'.$status),
            'starts_at' => Setting::getValue(self::SETTING_START),
            'ends_at' => Setting::getValue(self::SETTING_END), 'timezone' => config('app.timezone'),
            'products' => $rules['products'],
            'show_rewards' => Setting::bool(TreasureChestSettings::SHOW_REWARDS),
            'invite_url' => url('/register?ref='.$user->referral_code),
        ];
    }
}
