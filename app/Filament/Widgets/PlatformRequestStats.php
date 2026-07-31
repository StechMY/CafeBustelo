<?php

namespace App\Filament\Widgets;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\CafeDashboardMetricsService;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformRequestStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getHeading(): string
    {
        return __('filament.request_overview');
    }

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $metrics = app(CafeDashboardMetricsService::class)->requestOverview(
            DashboardFilterData::fromFilamentFilters($this->pageFilters),
            PlatformDashboardQuery::supportDownlineUserIds(),
        );

        return [
            Stat::make(__('filament.pending_recharge_requests'), number_format($metrics['pending_recharge_count']))
                ->description(PlatformDashboardQuery::money($metrics['pending_recharge_amount']))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make(__('filament.pending_withdraw_requests'), number_format($metrics['pending_withdraw_count']))
                ->description(PlatformDashboardQuery::money($metrics['pending_withdraw_amount']))
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make(__('filament.rejected_recharge_requests'), number_format($metrics['rejected_recharge_count']))
                ->description(__('filament.rejected_deposits_in_selected_range'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),

            Stat::make(__('filament.rejected_withdraw_requests'), number_format($metrics['rejected_withdraw_count']))
                ->description(__('filament.rejected_withdrawals_in_selected_range'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
