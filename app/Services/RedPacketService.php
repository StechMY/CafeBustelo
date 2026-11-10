<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseCoupon;
use App\Models\RedPacketDraw;
use App\Models\RedPacketEntry;
use App\Models\RedPacketPrize;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedPacketService
{
    public const SETTING_ENABLED = 'red_packet_enabled';
    public const SETTING_START = 'red_packet_starts_at';
    public const SETTING_END = 'red_packet_ends_at';

    public function __construct(private readonly WalletService $walletService) {}

    public function isActiveAt(?CarbonInterface $time = null): bool
    {
        $time ??= now();
        $start = Setting::getValue(self::SETTING_START);
        $end = Setting::getValue(self::SETTING_END);

        return Setting::bool(self::SETTING_ENABLED) && $start
            && $time->gte(Carbon::parse($start, config('app.timezone')))
            && (! $end || $time->lt(Carbon::parse($end, config('app.timezone'))));
    }

    public function handlePurchase(User $buyer, UserProduct $purchase): void
    {
        if ($buyer->is_virtual || ! $this->isActiveAt($purchase->purchased_at)
            || $purchase->product_type_snapshot === Product::TYPE_WEEKEND_SPECIAL) {
            return;
        }

        DB::transaction(function () use ($buyer, $purchase): void {
            // Purchase already holds this lock. Keep direct callers safe and awards idempotent too.
            $buyer = User::query()->whereKey($buyer->id)->lockForUpdate()->firstOrFail();
            $day = $purchase->purchased_at->copy()->timezone(config('app.timezone'))->toDateString();
            $previous = UserProduct::withTrashed()->where('user_id', $buyer->id)
                ->where('id', '<', $purchase->id)->where('product_type_snapshot', '!=', Product::TYPE_WEEKEND_SPECIAL)
                ->orderByDesc('id')->first();
            $code = strtoupper(str_replace('-', '', $purchase->meta['product_code_snapshot'] ?? ''));
            $price = (float) ($purchase->meta['original_price'] ?? $purchase->price_snapshot);
            $previousPrice = $previous ? (float) ($previous->meta['original_price'] ?? $previous->price_snapshot) : 0;
            if ($code !== 'T1' && $price > $previousPrice) {
                $this->award($buyer, 'upgrade:'.$purchase->id, 'upgrade', $day, $purchase);
            }

            $start = Carbon::parse(Setting::getValue(self::SETTING_START), config('app.timezone'));
            if (! $buyer->referrer_id || $buyer->referrer_id === $buyer->id || $buyer->created_at->lt($start)
                || UserProduct::withTrashed()->where('user_id', $buyer->id)->where('id', '<', $purchase->id)->exists()) {
                return;
            }
            $referrer = User::query()->whereKey($buyer->referrer_id)->lockForUpdate()->first();
            if (! $referrer || $referrer->is_virtual) {
                return;
            }
            $this->award($referrer, 'invite:'.$buyer->id, 'invite', $day, $purchase, $buyer);
            $count = RedPacketEntry::query()->where('user_id', $referrer->id)->where('source', 'invite')->where('earned_on', $day)->count();
            foreach ([3 => 1, 5 => 2] as $threshold => $chances) {
                if ($count < $threshold) continue;
                for ($i = 1; $i <= $chances; $i++) {
                    $this->award($referrer, "milestone:{$day}:{$threshold}:{$i}", 'milestone_'.$threshold, $day);
                }
            }
        }, 3);
    }

    private function award(User $user, string $key, string $source, string $day, ?UserProduct $purchase = null, ?User $invitee = null): void
    {
        RedPacketEntry::query()->firstOrCreate(['user_id' => $user->id, 'award_key' => $key], [
            'source' => $source, 'earned_on' => $day, 'user_product_id' => $purchase?->id, 'invited_user_id' => $invitee?->id,
        ]);
    }

    public function remainingChances(User $user): ?int
    {
        return $user->is_virtual ? null : RedPacketEntry::query()->where('user_id', $user->id)->whereNull('used_at')->count();
    }

    public function activity(User $user): array
    {
        return [
            'enabled' => $this->isActiveAt(), 'is_virtual' => (bool) $user->is_virtual,
            'remaining_chances' => $this->remainingChances($user),
            'today_invites' => RedPacketEntry::query()->where('user_id', $user->id)->where('source', 'invite')
                ->where('earned_on', now()->timezone(config('app.timezone'))->toDateString())->count(),
            'starts_at' => Setting::getValue(self::SETTING_START), 'ends_at' => Setting::getValue(self::SETTING_END),
            'timezone' => config('app.timezone'), 'invite_url' => url('/register?ref='.$user->referral_code),
            'prizes' => RedPacketPrize::query()->orderBy('slot')->get()->map(fn (RedPacketPrize $prize): array => [
                ...$prize->only(['id', 'slot', 'name', 'type', 'amount', 'discount_percent']),
                'available' => $prize->weight > 0 && ($prize->stock === null || $prize->stock > 0),
            ]),
        ];
    }

    public function draw(User $user, string $requestId): RedPacketDraw
    {
        return DB::transaction(function () use ($user, $requestId): RedPacketDraw {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = RedPacketDraw::query()->where('user_id', $user->id)->where('request_id', $requestId)->first();
            if ($existing) return $existing;
            if (! $this->isActiveAt()) throw new DomainException(__('red_packet.closed'));

            $entry = null;
            if (! $user->is_virtual) {
                $entry = RedPacketEntry::query()->where('user_id', $user->id)->whereNull('used_at')->orderBy('id')->lockForUpdate()->first();
                if (! $entry) throw new DomainException(__('red_packet.no_chances'));
            }
            $prizes = RedPacketPrize::query()->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (RedPacketPrize $prize): bool => $prize->weight > 0 && ($prize->stock === null || $prize->stock > 0));
            $total = (int) $prizes->sum('weight');
            if ($total < 1) throw new DomainException(__('red_packet.sold_out'));
            $ticket = random_int(1, $total);
            foreach ($prizes as $prize) {
                $ticket -= $prize->weight;
                if ($ticket <= 0) break;
            }
            $draw = RedPacketDraw::query()->create([
                'user_id' => $user->id, 'prize_id' => $prize->id, 'entry_id' => $entry?->id,
                'request_id' => $requestId, 'prize_name' => $prize->name, 'prize_type' => $prize->type,
                'amount' => $prize->amount, 'discount_percent' => $prize->discount_percent, 'is_virtual' => $user->is_virtual,
            ]);
            if ($prize->type === 'cash') {
                $this->walletService->credit(user: $user, walletType: Wallet::TYPE_WITHDRAWABLE, transactionType: 'red_packet',
                    amount: $prize->amount, reference: $draw, remarks: 'Red packet: '.$prize->name,
                    meta: ['red_packet_draw_id' => $draw->id, 'is_virtual' => $user->is_virtual]);
            } elseif ($prize->type === 'coupon') {
                PurchaseCoupon::query()->create(['user_id' => $user->id, 'red_packet_draw_id' => $draw->id, 'discount_percent' => $prize->discount_percent]);
            }
            if (! $user->is_virtual && $prize->stock !== null) $prize->decrement('stock');
            $entry?->update(['used_at' => now()]);

            return $draw;
        }, 3);
    }
}
