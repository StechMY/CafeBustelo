<?php

namespace App\Services;

use App\Models\CommissionLevel;
use App\Models\CommissionRecord;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Models\UserProduct;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class MyTeamReportService
{
    public function overview(User $user): array
    {
        $bUsers = $this->downlineUsers($user, 1);
        $cUsers = $this->downlineUsers($user, 2);
        $dUsers = $this->downlineUsers($user, 3);

        $allUsers = $bUsers
            ->merge($cUsers)
            ->merge($dUsers)
            ->unique('id')
            ->values();

        $allUserIds = $allUsers->pluck('id')->values();

        $stats = [
            'total_team_commission' => $this->totalTeamCommission($user),
            'total_valid_users' => $allUsers->where('status', 'active')->count(),
            'total_users' => $allUsers->count(),
            'team_accumulated_recharge' => $this->teamAccumulatedRecharge($allUsers),

            'b_count' => $bUsers->count(),
            'c_count' => $cUsers->count(),
            'd_count' => $dUsers->count(),

            'b_active_count' => $bUsers->where('status', 'active')->count(),
            'c_active_count' => $cUsers->where('status', 'active')->count(),
            'd_active_count' => $dUsers->where('status', 'active')->count(),

            'today_joined_count' => $this->joinedCount($allUsers, today()),
            'yesterday_joined_count' => $this->joinedCount($allUsers, today()->subDay()),

            'today_recharge' => $this->rechargeSumForDate($allUserIds, today()),
            'yesterday_recharge' => $this->rechargeSumForDate($allUserIds, today()->subDay()),

            'this_month_recharge' => $this->rechargeSumForMonth($allUserIds, now()->startOfMonth()),
            'last_month_recharge' => $this->rechargeSumForMonth($allUserIds, now()->subMonthNoOverflow()->startOfMonth()),
        ];

        return [
            'invite_url' => url('/register?ref='.$user->referral_code),
            'stats' => $this->applyDisplayOverrides($user, $stats),
            'commission_rates' => $this->commissionRates(),
            'levels' => [
                'b' => $bUsers,
                'c' => $cUsers,
                'd' => $dUsers,
            ],
        ];
    }

    public function level(
        User $user,
        string $level,
        string $status = 'all',
        int $page = 1,
        string $path = '',
        array $query = [],
    ): array {
        $levelNumber = $this->levelNumber($level);
        $users = $this->downlineUsers($user, $levelNumber);

        if ($status !== 'all') {
            $users = $users
                ->where('status', $status)
                ->values();
        }

        $summary = [
            'count' => $users->count(),
            'active_count' => $users->where('status', 'active')->count(),
            'inactive_count' => $users->where('status', 'inactive')->count(),
        ];

        $highestProducts = $this->highestProductByUserIds(
            $users->pluck('id')->values()
        );

        $users = $users->map(function (User $downline) use ($highestProducts): User {
            $downline->highest_product_snapshot = $highestProducts->get($downline->id);

            return $downline;
        });

        return [
            'level' => $level,
            'level_number' => $levelNumber,
            'level_label' => strtoupper($level),
            'status' => $status,
            'summary' => $summary,
            'downline_users' => $this->paginateCollection(
                collection: $users,
                perPage: 30,
                page: $page,
                path: $path,
                query: $query,
            ),
            'commission_rate' => $this->commissionRates()->get($levelNumber),
        ];
    }

    public function downlineUsers(User $user, int $depth): Collection
    {
        if (method_exists($user, 'allDownlineUsers')) {
            return $user->allDownlineUsers($depth)
                ->where('team_depth', $depth)
                ->values();
        }

        if ($depth === 1) {
            return User::query()
                ->where('referrer_id', $user->id)
                ->get()
                ->each(fn (User $downline) => $downline->team_depth = 1)
                ->values();
        }

        $previousLevelUsers = $this->downlineUsers($user, $depth - 1);

        if ($previousLevelUsers->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('referrer_id', $previousLevelUsers->pluck('id'))
            ->get()
            ->each(fn (User $downline) => $downline->team_depth = $depth)
            ->values();
    }

    public function levelNumber(string $level): int
    {
        return match ($level) {
            'b' => 1,
            'c' => 2,
            'd' => 3,
            default => throw new InvalidArgumentException('Invalid team level.'),
        };
    }

    private function commissionRates(): Collection
    {
        return CommissionLevel::query()
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->keyBy('level');
    }

    private function totalTeamCommission(User $user): float
    {
        return (float) CommissionRecord::query()
            ->where('earner_user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');
    }

    private function teamAccumulatedRecharge(Collection $users): float
    {
        return (float) $users->sum(fn (User $user): float => (float) $user->accumulated_recharge);
    }

    private function joinedCount(Collection $users, mixed $date): int
    {
        $date = CarbonImmutable::parse($date)->toDateString();

        return $users
            ->filter(fn (User $user): bool => $user->created_at?->toDateString() === $date)
            ->count();
    }

    private function rechargeSumForDate(Collection $userIds, mixed $date): float
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        $dateColumn = $this->rechargeDateColumn();

        return (float) RechargeRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate($dateColumn, CarbonImmutable::parse($date)->toDateString())
            ->sum('amount');
    }

    private function rechargeSumForMonth(Collection $userIds, mixed $month): float
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        $dateColumn = $this->rechargeDateColumn();
        $start = CarbonImmutable::parse($month)->startOfMonth();
        $end = $start->endOfMonth();

        return (float) RechargeRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween($dateColumn, [$start, $end])
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

    private function highestProductByUserIds(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return UserProduct::query()
            ->select([
                'user_products.id',
                'user_products.user_id',
                'user_products.product_id',
                'user_products.status',
                'user_products.price_snapshot',
                'products.name as product_name',
                'products.code as product_code',
            ])
            ->leftJoin('products', 'products.id', '=', 'user_products.product_id')
            ->whereIn('user_products.user_id', $userIds)
            ->orderByDesc('user_products.price_snapshot')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');
    }

    private function paginateCollection(
        Collection $collection,
        int $perPage,
        int $page,
        string $path,
        array $query = [],
    ): LengthAwarePaginatorContract {
        $page = max(1, $page);

        return new LengthAwarePaginator(
            items: $collection->forPage($page, $perPage)->values(),
            total: $collection->count(),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $path,
                'query' => $query,
            ],
        );
    }

    private function applyDisplayOverrides(User $user, array $stats): array
    {
        foreach ([
            'total_team_commission' => 'display_total_team_commission',
            'team_accumulated_recharge' => 'display_total_team_recharge',
            'total_users' => 'display_total_users_count',
            'total_valid_users' => 'display_valid_users_count',
            'b_count' => 'display_b_count',
            'b_active_count' => 'display_b_active_count',
            'c_count' => 'display_c_count',
            'c_active_count' => 'display_c_active_count',
            'd_count' => 'display_d_count',
            'd_active_count' => 'display_d_active_count',
            'today_joined_count' => 'display_today_joined_count',
            'yesterday_joined_count' => 'display_yesterday_joined_count',
            'today_recharge' => 'display_today_recharge',
            'yesterday_recharge' => 'display_yesterday_recharge',
            'this_month_recharge' => 'display_this_month_recharge',
            'last_month_recharge' => 'display_last_month_recharge',
        ] as $stat => $overrideField) {
            $stats[$stat] = $this->displayOverride($user, $overrideField, $stats[$stat]);
        }

        return $stats;
    }

    private function displayOverride(User $user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== ''
            ? $value
            : $realValue;
    }
}
