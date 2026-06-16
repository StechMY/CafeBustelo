<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\CommissionRecord;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MyTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $bUsers = $user->allDownlineUsers(1)->where('team_depth', 1)->values();
        $cUsers = $user->allDownlineUsers(2)->where('team_depth', 2)->values();
        $dUsers = $user->allDownlineUsers(3)->where('team_depth', 3)->values();
        $allUsers = $bUsers->merge($cUsers)->merge($dUsers)->unique('id')->values();
        $allUserIds = $allUsers->pluck('id')->values();

        $stats = [
            'total_team_commission' => ApiFormatter::money($this->displayOverride($user, 'display_total_team_commission', CommissionRecord::query()->where('earner_user_id', $user->id)->where('status', 'paid')->sum('amount'))),
            'total_valid_users' => (int) $this->displayOverride($user, 'display_valid_users_count', $allUsers->where('status', 'active')->count()),
            'total_users' => (int) $this->displayOverride($user, 'display_total_users_count', $allUsers->count()),
            'team_accumulated_recharge' => ApiFormatter::money($this->displayOverride($user, 'display_total_team_recharge', $allUsers->sum(fn (User $member): float => (float) $member->accumulated_recharge))),
            'b_count' => (int) $this->displayOverride($user, 'display_b_count', $bUsers->count()),
            'c_count' => (int) $this->displayOverride($user, 'display_c_count', $cUsers->count()),
            'd_count' => (int) $this->displayOverride($user, 'display_d_count', $dUsers->count()),
            'b_active_count' => (int) $this->displayOverride($user, 'display_b_active_count', $bUsers->where('status', 'active')->count()),
            'c_active_count' => (int) $this->displayOverride($user, 'display_c_active_count', $cUsers->where('status', 'active')->count()),
            'd_active_count' => (int) $this->displayOverride($user, 'display_d_active_count', $dUsers->where('status', 'active')->count()),
            'today_joined_count' => (int) $this->displayOverride($user, 'display_today_joined_count', $this->joinedCount($allUsers, today())),
            'yesterday_joined_count' => (int) $this->displayOverride($user, 'display_yesterday_joined_count', $this->joinedCount($allUsers, today()->subDay())),
            'today_recharge' => ApiFormatter::money($this->displayOverride($user, 'display_today_recharge', $this->rechargeSumForDate($allUserIds, today()))),
            'yesterday_recharge' => ApiFormatter::money($this->displayOverride($user, 'display_yesterday_recharge', $this->rechargeSumForDate($allUserIds, today()->subDay()))),
            'this_month_recharge' => ApiFormatter::money($this->displayOverride($user, 'display_this_month_recharge', $this->rechargeSumForMonth($allUserIds, now()->startOfMonth()))),
            'last_month_recharge' => ApiFormatter::money($this->displayOverride($user, 'display_last_month_recharge', $this->rechargeSumForMonth($allUserIds, now()->subMonthNoOverflow()->startOfMonth()))),
        ];

        return ApiResponse::success([
            'invite_url' => url('/register?ref='.$user->referral_code),
            'stats' => $stats,
            'commission_rates' => CommissionLevel::query()->where('is_active', true)->orderBy('level')->get()->map(fn ($level): array => [
                'level' => (int) $level->level,
                'level_label' => match ((int) $level->level) {
                    1 => 'B', 2 => 'C', 3 => 'D', default => (string) $level->level
                },
                'rate' => ApiFormatter::money($level->rate_percent),
            ])->values(),
            'levels' => [
                'b' => $this->teamMembers($bUsers),
                'c' => $this->teamMembers($cUsers),
                'd' => $this->teamMembers($dUsers),
            ],
        ]);
    }

    private function teamMembers(Collection $users): array
    {
        return $users->map(fn (User $user): array => [
            'id' => (int) $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'phone' => $user->phone,
            'status' => $user->status,
            'accumulated_recharge' => ApiFormatter::money($user->accumulated_recharge),
            'created_at' => ApiFormatter::dateTime($user->created_at),
        ])->values()->all();
    }

    private function joinedCount(Collection $users, mixed $date): int
    {
        $date = CarbonImmutable::parse($date)->toDateString();

        return $users->filter(fn (User $user): bool => $user->created_at?->toDateString() === $date)->count();
    }

    private function rechargeSumForDate(Collection $userIds, mixed $date): float
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        return (float) RechargeRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate($this->rechargeDateColumn(), CarbonImmutable::parse($date)->toDateString())
            ->sum('amount');
    }

    private function rechargeSumForMonth(Collection $userIds, mixed $month): float
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        $start = CarbonImmutable::parse($month)->startOfMonth();

        return (float) RechargeRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween($this->rechargeDateColumn(), [$start, $start->endOfMonth()])
            ->sum('amount');
    }

    private function rechargeDateColumn(): string
    {
        if (Schema::hasColumn('recharge_requests', 'approved_at')) {
            return 'approved_at';
        }

        if (Schema::hasColumn('recharge_requests', 'processed_at')) {
            return 'processed_at';
        }

        return 'updated_at';
    }

    private function displayOverride(User $user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== '' ? $value : $realValue;
    }
}
