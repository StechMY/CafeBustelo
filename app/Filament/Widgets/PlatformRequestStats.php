<?php

namespace App\Filament\Widgets;

use App\Models\RechargeRequest;
use App\Models\WithdrawRequest;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PlatformRequestStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Request Overview';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->pageFilters;

        $pendingRechargeCount = RechargeRequest::query()
            ->where('status', 'pending')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();

        $pendingRechargeAmount = RechargeRequest::query()
            ->where('status', 'pending')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $pendingWithdrawCount = WithdrawRequest::query()
            ->where('status', 'pending')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();

        $pendingWithdrawAmount = WithdrawRequest::query()
            ->where('status', 'pending')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $rejectedRechargeCount = RechargeRequest::query()
            ->where('status', 'rejected')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'reviewed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();

        $rejectedWithdrawCount = WithdrawRequest::query()
            ->where('status', 'rejected')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'reviewed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();

        return [
            Stat::make('Pending Recharge Requests', number_format($pendingRechargeCount))
                ->description(PlatformDashboardQuery::money($pendingRechargeAmount))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Pending Withdraw Requests', number_format($pendingWithdrawCount))
                ->description(PlatformDashboardQuery::money($pendingWithdrawAmount))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Rejected Recharge Requests', number_format($rejectedRechargeCount))
                ->description('Rejected deposits in selected range')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make('Rejected Withdraw Requests', number_format($rejectedWithdrawCount))
                ->description('Rejected withdrawals in selected range')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}