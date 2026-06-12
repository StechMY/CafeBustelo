<?php

namespace App\Http\Controllers;

use App\Models\CommissionLevel;
use App\Models\CommissionRecord;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Models\UserProduct;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MyTeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $bUsers = $this->downlineUsers($user, 1);
        $cUsers = $this->downlineUsers($user, 2);
        $dUsers = $this->downlineUsers($user, 3);

        $allUsers = $bUsers
            ->merge($cUsers)
            ->merge($dUsers)
            ->unique('id')
            ->values();

        $allUserIds = $allUsers->pluck('id')->values();

        $commissionRates = $this->commissionRates();

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

        $stats = $this->applyDisplayOverrides($user, $stats);

        return view('my-team.index', [
            'user' => $user,
            'inviteUrl' => url('/register?ref=' . $user->referral_code),
            'stats' => $stats,
            'commissionRates' => $commissionRates,
        ]);
    }

    public function level(Request $request, string $level): View
    {
        $user = $request->user();

        $levelNumber = match ($level) {
            'b' => 1,
            'c' => 2,
            'd' => 3,
        };

        $levelLabel = strtoupper($level);

        $status = $request->query('status', 'all');

        $users = $this->downlineUsers($user, $levelNumber);

        if ($status !== 'all') {
            $users = $users
                ->where('status', $status)
                ->values();
        }

        $highestProducts = $this->highestProductByUserIds(
            $users->pluck('id')->values()
        );

        $users = $users->map(function (User $downline) use ($highestProducts): User {
            $downline->highest_product_snapshot = $highestProducts->get($downline->id);

            return $downline;
        });

        $paginatedUsers = $this->paginateCollection(
            collection: $users,
            perPage: 30,
            page: (int) $request->query('page', 1),
            path: route('my-team.level', $level),
            query: $request->query(),
        );

        return view('my-team.level', [
            'user' => $user,
            'level' => $level,
            'levelNumber' => $levelNumber,
            'levelLabel' => $levelLabel,
            'status' => $status,
            'downlineUsers' => $paginatedUsers,
            'commissionRate' => $this->commissionRates()->get($levelNumber),
        ]);
    }

    private function downlineUsers(User $user, int $depth): Collection
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
                ->each(fn(User $downline) => $downline->team_depth = 1)
                ->values();
        }

        $previousLevelUsers = $this->downlineUsers($user, $depth - 1);

        if ($previousLevelUsers->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('referrer_id', $previousLevelUsers->pluck('id'))
            ->get()
            ->each(fn(User $downline) => $downline->team_depth = $depth)
            ->values();
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
        return (float) $users->sum(fn(User $user): float => (float) $user->accumulated_recharge);
    }

    private function joinedCount(Collection $users, mixed $date): int
    {
        $date = CarbonImmutable::parse($date)->toDateString();

        return $users
            ->filter(fn(User $user): bool => $user->created_at?->toDateString() === $date)
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
        $stats['total_team_commission'] = (float) $this->displayOverride(
            $user,
            'display_total_team_commission',
            $stats['total_team_commission']
        );

        $stats['team_accumulated_recharge'] = (float) $this->displayOverride(
            $user,
            'display_total_team_recharge',
            $stats['team_accumulated_recharge']
        );

        $stats['total_users'] = (int) $this->displayOverride(
            $user,
            'display_total_users_count',
            $stats['total_users']
        );

        $stats['total_valid_users'] = (int) $this->displayOverride(
            $user,
            'display_valid_users_count',
            $stats['total_valid_users']
        );

        $stats['b_count'] = (int) $this->displayOverride(
            $user,
            'display_b_count',
            $stats['b_count']
        );

        $stats['b_active_count'] = (int) $this->displayOverride(
            $user,
            'display_b_active_count',
            $stats['b_active_count']
        );

        $stats['c_count'] = (int) $this->displayOverride(
            $user,
            'display_c_count',
            $stats['c_count']
        );

        $stats['c_active_count'] = (int) $this->displayOverride(
            $user,
            'display_c_active_count',
            $stats['c_active_count']
        );

        $stats['d_count'] = (int) $this->displayOverride(
            $user,
            'display_d_count',
            $stats['d_count']
        );

        $stats['d_active_count'] = (int) $this->displayOverride(
            $user,
            'display_d_active_count',
            $stats['d_active_count']
        );

        $stats['today_joined_count'] = (int) $this->displayOverride(
            $user,
            'display_today_joined_count',
            $stats['today_joined_count']
        );

        $stats['yesterday_joined_count'] = (int) $this->displayOverride(
            $user,
            'display_yesterday_joined_count',
            $stats['yesterday_joined_count']
        );

        $stats['today_recharge'] = (float) $this->displayOverride(
            $user,
            'display_today_recharge',
            $stats['today_recharge']
        );

        $stats['yesterday_recharge'] = (float) $this->displayOverride(
            $user,
            'display_yesterday_recharge',
            $stats['yesterday_recharge']
        );

        $stats['this_month_recharge'] = (float) $this->displayOverride(
            $user,
            'display_this_month_recharge',
            $stats['this_month_recharge']
        );

        $stats['last_month_recharge'] = (float) $this->displayOverride(
            $user,
            'display_last_month_recharge',
            $stats['last_month_recharge']
        );

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