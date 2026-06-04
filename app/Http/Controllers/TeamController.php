<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $level = $request->query('level', 'all');
        $status = $request->query('status', 'all');

        $downlineUsers = $this->getDownlineUsers(
            user: $user,
            level: $level,
            status: $status,
        );

        $paginatedDownlineUsers = $this->paginateCollection(
            collection: $downlineUsers,
            perPage: 20,
            page: (int) $request->query('page', 1),
            path: route('team.index'),
            query: $request->query(),
        );

        $bUsers = $user->teamUsersByLevel(1);
        $cUsers = $user->teamUsersByLevel(2);
        $dUsers = $user->teamUsersByLevel(3);
        $allUsers = $user->allDownlineUsers();

        return view('team.index', [
            'user' => $user,
            'level' => $level,
            'status' => $status,
            'downlineUsers' => $paginatedDownlineUsers,

            'stats' => [
                'b_total' => $bUsers->count(),
                'c_total' => $cUsers->count(),
                'd_total' => $dUsers->count(),
                'all_total' => $allUsers->count(),

                'b_active' => $bUsers->where('status', 'active')->count(),
                'c_active' => $cUsers->where('status', 'active')->count(),
                'd_active' => $dUsers->where('status', 'active')->count(),
                'all_active' => $allUsers->where('status', 'active')->count(),
            ],

            'commissionRates' => \App\Models\CommissionLevel::query()
                ->where('is_active', true)
                ->orderBy('level')
                ->get()
                ->keyBy('level'),
        ]);
    }

    private function getDownlineUsers(User $user, string $level, string $status): Collection
    {
        $users = match ($level) {
            '1', 'b', 'B' => $user->allDownlineUsers(1)->where('team_depth', 1)->values(),
            '2', 'c', 'C' => $user->allDownlineUsers(2)->where('team_depth', 2)->values(),
            '3', 'd', 'D' => $user->allDownlineUsers(3)->where('team_depth', 3)->values(),
            default => $user->allDownlineUsers(),
        };

        if ($status !== 'all') {
            $users = $users
                ->where('status', $status)
                ->values();
        }

        return $users->values();
    }

    private function paginateCollection(
        Collection $collection,
        int $perPage,
        int $page,
        string $path,
        array $query = [],
    ): LengthAwarePaginator {
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
}