<?php

namespace App\Services;

use App\Models\CheckInRecord;
use App\Models\User;
use App\Models\VipLevel;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckInService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly SalaryService $salaryService,
    ) {}

    public function claim(
        User $user,
        ?string $ip = null,
        ?string $userAgent = null,
    ): CheckInRecord {
        return DB::transaction(function () use ($user, $ip, $userAgent): CheckInRecord {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->status !== 'active') {
                throw new RuntimeException(__('checkin.account_not_active'));
            }

            if (! (bool) $lockedUser->check_in_status) {
                throw new RuntimeException(__('checkin.disabled'));
            }

            $today = now()->toDateString();

            $alreadyClaimed = CheckInRecord::query()
                ->where('user_id', $lockedUser->id)
                ->whereDate('claim_date', $today)
                ->exists();

            if ($alreadyClaimed) {
                throw new RuntimeException(__('checkin.already_claimed'));
            }

            $vipLevel = $this->salaryService->currentVipLevel($lockedUser);

            if (! $vipLevel) {
                $vipLevel = VipLevel::query()
                    ->where('status', 'active')
                    ->orderBy('level')
                    ->first();
            }

            if (! $vipLevel) {
                throw new RuntimeException(__('checkin.no_vip_level'));
            }

            $amount = round((float) $vipLevel->daily_check_in_amount, 2);

            if ($amount <= 0) {
                throw new RuntimeException(__('checkin.no_amount'));
            }

            $record = CheckInRecord::query()->create([
                'user_id' => $lockedUser->id,
                'vip_level_id' => $vipLevel->id,
                'claim_date' => $today,
                'vip_level_snapshot' => $vipLevel->level,
                'amount' => $amount,
                'claimed_at' => now(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'meta' => [
                    'vip_name' => $vipLevel->name,
                    'position_name' => $vipLevel->position_name,
                ],
            ]);

            $this->walletService->credit(
                user: $lockedUser,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'check_in',
                amount: $amount,
                reference: $record,
                remarks: 'Daily check-in reward',
                meta: [
                    'check_in_record_id' => $record->id,
                    'vip_level_id' => $vipLevel->id,
                    'vip_level_snapshot' => $vipLevel->level,
                    'claim_date' => $today,
                ],
            );

            return $record;
        });
    }

    public function hasClaimedToday(User $user): bool
    {
        return CheckInRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('claim_date', now()->toDateString())
            ->exists();
    }

    public function todayRecord(User $user): ?CheckInRecord
    {
        return CheckInRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('claim_date', now()->toDateString())
            ->first();
    }
}