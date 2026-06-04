<?php

namespace App\Filament\Widgets;

use App\Models\RechargeRequest;
use App\Models\WithdrawRequest;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PlatformFinanceChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Approved Recharge vs Withdraw';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $filters = $this->pageFilters;

        $start = PlatformDashboardQuery::startDate($filters) ?? now()->startOfMonth();
        $end = PlatformDashboardQuery::endDate($filters) ?? now()->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $labels = [];
        $rechargeData = [];
        $withdrawData = [];
        $netData = [];

        foreach (CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->startOfDay()) as $date) {
            /** @var Carbon $date */
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $approvedRecharge = RechargeRequest::query()
                ->where('status', 'approved')
                ->whereBetween('reviewed_at', [$dayStart, $dayEnd])
                ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
                ->sum('amount');

            $approvedWithdraw = WithdrawRequest::query()
                ->where('status', 'approved')
                ->whereBetween('reviewed_at', [$dayStart, $dayEnd])
                ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
                ->sum('amount');

            $labels[] = $date->format('d M');
            $rechargeData[] = round((float) $approvedRecharge, 2);
            $withdrawData[] = round((float) $approvedWithdraw, 2);
            $netData[] = round((float) $approvedRecharge - (float) $approvedWithdraw, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Approved Recharge',
                    'data' => $rechargeData,
                ],
                [
                    'label' => 'Approved Withdraw',
                    'data' => $withdrawData,
                ],
                [
                    'label' => 'Net',
                    'data' => $netData,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}