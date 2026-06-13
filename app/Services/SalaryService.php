<?php

namespace App\Services;

use App\Models\SalaryClaim;
use App\Models\SalaryClaimWindow;
use App\Models\User;
use App\Models\VipLevel;
use App\Models\Wallet;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SalaryService
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {
    }

    public function currentVipLevel(User $user): ?VipLevel
    {
        if ($user->display_vip_level != null && $user->display_vip_level != '') {
            return VipLevel::query()->where('status', 'active')->where('level', $user->display_vip_level)->first();
        }
        $activeTeamSize = $user->activeTeamSize();

        return VipLevel::query()
            ->where('status', 'active')
            ->where('required_accumulated_recharge', '<=', $user->accumulated_recharge)
            ->where('required_active_team_size', '<=', $activeTeamSize)
            ->orderByDesc('level')
            ->first();
    }

    public function claim(
        User $user,
        string $type,
        ?string $ip = null,
        ?string $userAgent = null,
    ): SalaryClaim {
        $this->validateType($type);

        return DB::transaction(function () use ($user, $type, $ip, $userAgent): SalaryClaim {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureUserCanClaimType($lockedUser, $type);
            $this->ensureWithinClaimWindow($type);

            $periodKey = $this->periodKey($type);

            $alreadyClaimed = SalaryClaim::query()
                ->where('user_id', $lockedUser->id)
                ->where('type', $type)
                ->where('period_key', $periodKey)
                ->exists();

            if ($alreadyClaimed) {
                throw new RuntimeException(__('salary.already_claimed'));
            }

            $activeTeamSize = $lockedUser->activeTeamSize();

            $vipLevel = $this->currentVipLevel($lockedUser);

            if (!$vipLevel) {
                throw new RuntimeException(__('salary.not_eligible'));
            }

            $salaryAmount = round($vipLevel->salaryAmountForType($type), 2);
            $teamSubsidyAmount = round($vipLevel->teamSubsidyForType($type), 2);
            $totalAmount = round($salaryAmount + $teamSubsidyAmount, 2);

            if ($totalAmount <= 0) {
                throw new RuntimeException(__('salary.no_salary_amount'));
            }

            $claim = SalaryClaim::query()->create([
                'user_id' => $lockedUser->id,
                'vip_level_id' => $vipLevel->id,
                'type' => $type,
                'period_key' => $periodKey,
                'vip_level_snapshot' => $vipLevel->level,
                'accumulated_recharge_snapshot' => $lockedUser->accumulated_recharge,
                'active_team_size_snapshot' => $activeTeamSize,
                'salary_amount' => $salaryAmount,
                'team_subsidy_amount' => $teamSubsidyAmount,
                'total_amount' => $totalAmount,
                'claimed_at' => now(),
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'meta' => [
                    'vip_name' => $vipLevel->name,
                    'position_name' => $vipLevel->position_name,
                    'claim_window' => $this->claimWindowLabel($type),
                ],
            ]);

            $this->walletService->credit(
                user: $lockedUser,
                walletType: Wallet::TYPE_WITHDRAWABLE,
                transactionType: 'salary_' . $type,
                amount: $totalAmount,
                reference: $claim,
                remarks: ucfirst($type) . ' salary claim',
                meta: [
                    'salary_claim_id' => $claim->id,
                    'vip_level_id' => $vipLevel->id,
                    'period_key' => $periodKey,
                    'vip_level_snapshot' => $vipLevel->level,
                    'salary_amount' => $salaryAmount,
                    'team_subsidy_amount' => $teamSubsidyAmount,
                ],
            );

            return $claim;
        });
    }

    public function periodKey(string $type, ?Carbon $date = null): string
    {
        $window = $this->claimWindow($type);
        $timezone = $window?->timezone ?: config('app.timezone');

        $date ??= now($timezone);

        return match ($type) {
            SalaryClaim::TYPE_WEEKLY => $date
                ->copy()
                ->startOfWeek(CarbonInterface::SUNDAY)
                ->format('Y-m-d'),

            SalaryClaim::TYPE_MONTHLY => $date->format('Y-m'),

            SalaryClaim::TYPE_ANNUAL => $date->format('Y'),

            default => throw new RuntimeException('Invalid salary type.'),
        };
    }

    public function hasClaimed(User $user, string $type): bool
    {
        return SalaryClaim::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('period_key', $this->periodKey($type))
            ->exists();
    }

    public function claimWindow(string $type): ?SalaryClaimWindow
    {
        return SalaryClaimWindow::query()
            ->where('type', $type)
            ->first();
    }

    public function isWithinClaimWindow(string $type): bool
    {
        $window = $this->claimWindow($type);

        if (!$window || !$window->is_enabled) {
            return false;
        }

        $timezone = $window->timezone ?: config('app.timezone');
        $now = now($timezone);

        $start = $now->copy()->setTimeFromTimeString((string) $window->start_time);
        $end = $now->copy()->setTimeFromTimeString((string) $window->end_time);

        $validDate = match ($type) {
            SalaryClaim::TYPE_WEEKLY => (int) $now->dayOfWeek === (int) $window->day_of_week,
            SalaryClaim::TYPE_MONTHLY => (int) $now->day === (int) $window->day_of_month,
            SalaryClaim::TYPE_ANNUAL => (int) $now->month === (int) $window->month
            && (int) $now->day === (int) $window->day,
            default => false,
        };

        return $validDate && $now->betweenIncluded($start, $end);
    }

    public function claimWindowLabel(string $type): string
    {
        $window = $this->claimWindow($type);

        if (!$window || !$window->is_enabled) {
            return __('salary.claim_window_disabled');
        }

        $start = substr((string) $window->start_time, 0, 5);
        $end = substr((string) $window->end_time, 0, 5);

        return match ($type) {
            SalaryClaim::TYPE_WEEKLY => __('salary.weekly_claim_window_label', [
                'day' => __('salary.days.' . $window->day_of_week),
                'start' => $start,
                'end' => $end,
            ]),

            SalaryClaim::TYPE_MONTHLY => __('salary.monthly_claim_window_label', [
                'day' => $window->day_of_month,
                'start' => $start,
                'end' => $end,
            ]),

            SalaryClaim::TYPE_ANNUAL => __('salary.annual_claim_window_label', [
                'month' => $window->month,
                'day' => $window->day,
                'start' => $start,
                'end' => $end,
            ]),

            default => '-',
        };
    }

    private function ensureWithinClaimWindow(string $type): void
    {
        if (!$this->isWithinClaimWindow($type)) {
            throw new RuntimeException(__('salary.claim_window_closed'));
        }
    }

    private function validateType(string $type): void
    {
        if (
            !in_array($type, [
                SalaryClaim::TYPE_WEEKLY,
                SalaryClaim::TYPE_MONTHLY,
                SalaryClaim::TYPE_ANNUAL,
            ], true)
        ) {
            throw new RuntimeException(__('salary.invalid_type'));
        }
    }

    private function ensureUserCanClaimType(User $user, string $type): void
    {
        if ($user->status !== 'active') {
            throw new RuntimeException(__('salary.account_not_active'));
        }

        $enabled = match ($type) {
            SalaryClaim::TYPE_WEEKLY => (bool) $user->weekly_salary_status,
            SalaryClaim::TYPE_MONTHLY => (bool) $user->monthly_salary_status,
            SalaryClaim::TYPE_ANNUAL => (bool) $user->annual_salary_status,
            default => false,
        };

        if (!$enabled) {
            throw new RuntimeException(__('salary.claim_disabled'));
        }
    }
}