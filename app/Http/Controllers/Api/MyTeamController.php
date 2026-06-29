<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MyTeamReportService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyTeamController extends Controller
{
    public function index(Request $request, MyTeamReportService $myTeamReportService): JsonResponse
    {
        $overview = $myTeamReportService->overview($request->user());
        $stats = $this->formatStats($overview['stats']);

        return ApiResponse::success([
            'invite_url' => $overview['invite_url'],
            'stats' => $stats,
            'summary' => $stats,
            'commission_rates' => $this->formatCommissionRates($overview['commission_rates']),
            'levels' => [
                'b' => $this->formatLevelOverview('b', $overview),
                'c' => $this->formatLevelOverview('c', $overview),
                'd' => $this->formatLevelOverview('d', $overview),
            ],
        ]);
    }

    public function level(Request $request, string $level, MyTeamReportService $myTeamReportService): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:all,active,inactive'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $report = $myTeamReportService->level(
            user: $request->user(),
            level: $level,
            status: $validated['status'] ?? 'all',
            page: (int) ($validated['page'] ?? 1),
            path: url('/api/v1/my-team/'.$level),
            query: $request->query(),
        );
        $users = $report['downline_users'];

        return ApiResponse::success([
            'level' => $report['level'],
            'level_number' => $report['level_number'],
            'level_label' => $report['level_label'],
            'status' => $report['status'],
            'commission_rate' => $report['commission_rate']
                ? $this->formatCommissionRate($report['commission_rate'])
                : null,
            'summary' => $report['summary'],
            'data' => $users->getCollection()
                ->map(fn (User $user): array => $this->formatLevelMember($user))
                ->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }

    private function formatLevelOverview(string $level, array $overview): array
    {
        $levelNumber = match ($level) {
            'b' => 1,
            'c' => 2,
            'd' => 3,
        };
        $users = $overview['levels'][$level];
        $rate = $overview['commission_rates']->get($levelNumber);

        return [
            'level' => $level,
            'level_number' => $levelNumber,
            'label' => strtoupper($level),
            'rate' => ApiFormatter::money($rate?->rate_percent),
            'formatted_rate' => number_format((float) ($rate?->rate_percent ?? 0), 2).'%',
            'count' => (int) $overview['stats'][$level.'_count'],
            'active_count' => (int) $overview['stats'][$level.'_active_count'],
            'real_count' => $users->count(),
            'real_active_count' => $users->where('status', 'active')->count(),
            'url' => url('/api/v1/my-team/'.$level),
        ];
    }

    private function formatCommissionRates(mixed $commissionRates): array
    {
        return $commissionRates
            ->map(fn ($commissionRate): array => $this->formatCommissionRate($commissionRate))
            ->values()
            ->all();
    }

    private function formatCommissionRate(mixed $commissionRate): array
    {
        return [
            'level' => (int) $commissionRate->level,
            'level_label' => match ((int) $commissionRate->level) {
                1 => 'B',
                2 => 'C',
                3 => 'D',
                default => (string) $commissionRate->level,
            },
            'rate' => ApiFormatter::money($commissionRate->rate_percent),
            'formatted_rate' => number_format((float) $commissionRate->rate_percent, 2).'%',
        ];
    }

    private function formatLevelMember(User $user): array
    {
        $phone = preg_replace('/\D+/', '', (string) $user->phone);
        $lastFivePhone = $phone ? substr($phone, -5) : '-';
        $highestProduct = $user->highest_product_snapshot;

        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'phone' => $user->phone,
            'phone_last_5' => $lastFivePhone,
            'masked_phone' => $lastFivePhone === '-' ? '-' : '*****'.$lastFivePhone,
            'status' => $user->status,
            'status_label' => __('status.'.$user->status),
            'team_depth' => (int) $user->team_depth,
            'highest_product' => $highestProduct ? [
                'id' => (int) $highestProduct->id,
                'product_id' => (int) $highestProduct->product_id,
                'product_code' => $highestProduct->product_code,
                'product_name' => $highestProduct->product_name,
                'status' => $highestProduct->status,
                'price_snapshot' => ApiFormatter::money($highestProduct->price_snapshot),
                'formatted_price_snapshot' => ApiFormatter::formattedMoney($highestProduct->price_snapshot),
            ] : null,
            'created_at' => ApiFormatter::dateTime($user->created_at),
            'created_at_label' => $user->created_at
                ? $user->created_at->copy()->timezone(config('app.timezone'))->format('d M Y')
                : null,
        ];
    }

    private function formatStats(array $stats): array
    {
        foreach ([
            'total_team_commission',
            'team_accumulated_recharge',
            'today_recharge',
            'yesterday_recharge',
            'this_month_recharge',
            'last_month_recharge',
        ] as $key) {
            $stats[$key] = ApiFormatter::money($stats[$key]);
            $stats['formatted_'.$key] = ApiFormatter::formattedMoney($stats[$key]);
        }

        foreach ([
            'total_valid_users',
            'total_users',
            'b_count',
            'c_count',
            'd_count',
            'b_active_count',
            'c_active_count',
            'd_active_count',
            'today_joined_count',
            'yesterday_joined_count',
        ] as $key) {
            $stats[$key] = (int) $stats[$key];
        }

        return $stats;
    }
}
