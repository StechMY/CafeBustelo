<?php

namespace App\Services;

use App\Models\GoldenEggDraw;
use App\Models\GoldenEggEntry;
use App\Models\GoldenEggPrize;
use App\Models\PurchaseCoupon;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoldenEggService
{
    public const SETTING_ENABLED = 'golden_egg_enabled';

    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function registerInvitee(User $invitee): void
    {
        if (! $invitee->referrer_id || $invitee->is_virtual || ! Setting::bool(self::SETTING_ENABLED)) {
            return;
        }

        GoldenEggEntry::query()->firstOrCreate(
            ['invited_user_id' => $invitee->id],
            ['user_id' => $invitee->referrer_id],
        );
    }

    public function qualifyFirstPurchase(User $invitee, UserProduct $purchase): void
    {
        if (! $invitee->referrer_id || $invitee->is_virtual || ! Setting::bool(self::SETTING_ENABLED)
            || UserProduct::query()->where('user_id', $invitee->id)->whereKeyNot($purchase->id)->exists()) {
            return;
        }

        GoldenEggEntry::query()->where('invited_user_id', $invitee->id)
            ->whereNull('qualified_at')->update(['qualified_at' => now()]);
    }

    public function remainingChances(User $user): ?int
    {
        return $user->is_virtual ? null : GoldenEggEntry::query()
            ->where('user_id', $user->id)->whereNotNull('qualified_at')->whereNull('used_at')->count();
    }

    public function givePurchaseChance(User $buyer, UserProduct $purchase): void
    {
        if ($buyer->is_virtual || ! Setting::bool(self::SETTING_ENABLED)) {
            return;
        }

        GoldenEggEntry::query()->firstOrCreate(
            ['user_product_id' => $purchase->id],
            ['user_id' => $buyer->id, 'qualified_at' => now()],
        );
    }

    public function draw(User $user, string $requestId, int $eggNumber): GoldenEggDraw
    {
        if ($eggNumber < 1 || $eggNumber > 9) {
            throw new RuntimeException(__('golden_egg.invalid_egg'));
        }

        return DB::transaction(function () use ($user, $requestId, $eggNumber): GoldenEggDraw {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            // The same request always returns its original award, including after a lost response.
            $existing = GoldenEggDraw::query()->where('user_id', $user->id)->where('request_id', $requestId)->first();
            if ($existing) {
                return $existing;
            }

            if (! $user->is_virtual && ! Setting::bool(self::SETTING_ENABLED)) {
                throw new RuntimeException(__('golden_egg.closed'));
            }

            $entry = null;
            if (! $user->is_virtual) {
                $entry = GoldenEggEntry::query()->where('user_id', $user->id)
                    ->whereNotNull('qualified_at')->whereNull('used_at')->orderBy('id')->lockForUpdate()->first();
                if (! $entry) {
                    throw new RuntimeException(__('golden_egg.no_chances'));
                }
            }

            // Lock in a consistent order before filtering so the last unit cannot be awarded twice.
            $prizes = GoldenEggPrize::query()->orderBy('id')->lockForUpdate()->get()
                ->filter(fn (GoldenEggPrize $prize): bool => $user->is_virtual || ($prize->weight > 0
                    && ($prize->stock === null || $prize->stock > 0)));
            $totalWeight = $user->is_virtual ? $prizes->count() : (int) $prizes->sum('weight');
            if ($totalWeight < 1) {
                throw new RuntimeException(__('golden_egg.sold_out'));
            }

            $ticket = random_int(1, $totalWeight);
            foreach ($prizes as $prize) {
                $ticket -= $user->is_virtual ? 1 : $prize->weight;
                if ($ticket <= 0) {
                    break;
                }
            }

            $draw = GoldenEggDraw::query()->create([
                'user_id' => $user->id, 'prize_id' => $prize->id, 'entry_id' => $entry?->id,
                'request_id' => $requestId, 'egg_number' => $eggNumber, 'prize_name' => $prize->name,
                'prize_type' => $prize->type, 'amount' => $prize->amount,
                'discount_percent' => $prize->discount_percent, 'is_virtual' => $user->is_virtual,
            ]);

            if ($prize->type === 'cash') {
                $this->walletService->credit(
                    user: $user, walletType: Wallet::TYPE_WITHDRAWABLE, transactionType: 'golden_egg',
                    amount: $prize->amount, reference: $draw, remarks: 'Golden egg: '.$prize->name,
                    meta: ['golden_egg_draw_id' => $draw->id, 'is_virtual' => $user->is_virtual],
                );
            } else {
                PurchaseCoupon::query()->create([
                    'user_id' => $user->id, 'golden_egg_draw_id' => $draw->id,
                    'discount_percent' => $prize->discount_percent,
                ]);
            }

            if (! $user->is_virtual && $prize->stock !== null) {
                $prize->decrement('stock');
            }
            $entry?->update(['used_at' => now()]);

            return $draw;
        }, 3);
    }

    public function formatDraw(GoldenEggDraw $draw): array
    {
        return [
            'id' => $draw->id, 'prize_id' => (int) $draw->prize_id, 'egg_number' => (int) $draw->egg_number,
            'name' => $draw->prize_name, 'type' => $draw->prize_type,
            'amount' => $draw->amount, 'discount_percent' => $draw->discount_percent,
            'created_at' => $draw->created_at->toIso8601String(),
        ];
    }
}
