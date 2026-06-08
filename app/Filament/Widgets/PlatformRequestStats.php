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

    protected function getHeading(): string
    {
        return __('filament.request_overview');
    }

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
            Stat::make(__('filament.pending_recharge_requests'), number_format($pendingRechargeCount))
                ->description(PlatformDashboardQuery::money($pendingRechargeAmount))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make(__('filament.pending_withdraw_requests'), number_format($pendingWithdrawCount))
                ->description(PlatformDashboardQuery::money($pendingWithdrawAmount))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make(__('filament.rejected_recharge_requests'), number_format($rejectedRechargeCount))
                ->description(__('filament.rejected_deposits_in_selected_range'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make(__('filament.rejected_withdraw_requests'), number_format($rejectedWithdrawCount))
                ->description(__('filament.rejected_withdrawals_in_selected_range'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}