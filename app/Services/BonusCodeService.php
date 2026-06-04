<?php

namespace App\Services;

use App\Models\AdminUser;
use App\Models\BonusClaim;
use App\Models\BonusCode;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BonusCodeService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function generateUniqueCode(string $type): string
    {
        $prefix = match ($type) {
            BonusCode::TYPE_INVITE => 'INV',
            BonusCode::TYPE_UPGRADE => 'UPG',
            BonusCode::TYPE_COMPENSATION => 'COM',
            BonusCode::TYPE_ADMIN => 'ADM',
            default => 'BON',
        };

        do {
            $code = $prefix . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        } while (BonusCode::query()->where('code', $code)->exists());

        return $code;
    }

    public function createForUser(
        User $owner,
        string $type,
        float|string $amount,
        ?AdminUser $adminUser = null,
        ?string $remark = null,
        mixed $expiresAt = null,
        array $meta = [],
        ?string $code = null,
    ): BonusCode {
        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Bonus amount must be greater than zero.');
        }

        if (! in_array($type, [
            BonusCode::TYPE_INVITE,
            BonusCode::TYPE_UPGRADE,
            BonusCode::TYPE_ADMIN,
            BonusCode::TYPE_COMPENSATION,
        ], true)) {
            throw new RuntimeException('Invalid bonus code type.');
        }

        $code = $code
            ? strtoupper(trim($code))
            : $this->generateUniqueCode($type);

        return BonusCode::query()->create([
            'code' => $code,
            'owner_user_id' => $owner->id,
            'issued_by_admin_user_id' => $adminUser?->id,
            'type' => $type,
            'amount' => $amount,
            'status' => BonusCode::STATUS_UNUSED,
            'expires_at' => $expiresAt,
            'remark' => $remark,
            'meta' => $meta ?: null,
        ]);
    }

    public function claim(User $user, BonusCode $bonusCode): BonusClaim
    {
        return DB::transaction(function () use ($user, $bonusCode): BonusClaim {
            $lockedBonusCode = BonusCode::query()
                ->whereKey($bonusCode->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedBonusCode->isExpiredByTime() && $lockedBonusCode->isUnused()) {
                $lockedBonusCode->forceFill([
                    'status' => BonusCode::STATUS_EXPIRED,
                ])->save();

                throw new RuntimeException(__('bonus.code_expired'));
            }

            if (! $lockedBonusCode->isUnused()) {
                throw new RuntimeException(__('bonus.code_not_available'));
            }

            if ((int) $lockedBonusCode->owner_user_id !== (int) $user->id) {
                throw new RuntimeException(__('bonus.code_not_belong_to_you'));
            }

            $alreadyClaimed = BonusClaim::query()
                ->where('bonus_code_id', $lockedBonusCode->id)
                ->exists();

            if ($alreadyClaimed) {
                throw new RuntimeException(__('bonus.code_already_claimed'));
            }

            $claim = BonusClaim::query()->create([
                'user_id' => $user->id,
                'bonus_code_id' => $lockedBonusCode->id,
                'type' => $lockedBonusCode->type,
                'amount' => $lockedBonusCode->amount,
                'claimed_at' => now(),
                'meta' => [
                    'bonus_code' => $lockedBonusCode->code,
                    'bonus_type' => $lockedBonusCode->type,
                    'issued_by_admin_user_id' => $lockedBonusCode->issued_by_admin_user_id,
                ],
            ]);

            $lockedBonusCode->forceFill([
                'status' => BonusCode::STATUS_USED,
                'used_at' => now(),
            ])->save();

            $this->walletService->credit(
                user: $user,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'bonus_code',
                amount: $lockedBonusCode->amount,
                reference: $claim,
                remarks: 'Bonus code claimed: ' . $lockedBonusCode->code,
                meta: [
                    'bonus_code_id' => $lockedBonusCode->id,
                    'bonus_code' => $lockedBonusCode->code,
                    'bonus_type' => $lockedBonusCode->type,
                ],
            );

            return $claim;
        });
    }

    public function cancel(BonusCode $bonusCode, ?string $remark = null): BonusCode
    {
        return DB::transaction(function () use ($bonusCode, $remark): BonusCode {
            $lockedBonusCode = BonusCode::query()
                ->whereKey($bonusCode->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedBonusCode->isUnused()) {
                throw new RuntimeException('Only unused bonus codes can be cancelled.');
            }

            $lockedBonusCode->forceFill([
                'status' => BonusCode::STATUS_CANCELLED,
                'remark' => $remark ?: $lockedBonusCode->remark,
            ])->save();

            return $lockedBonusCode;
        });
    }
}