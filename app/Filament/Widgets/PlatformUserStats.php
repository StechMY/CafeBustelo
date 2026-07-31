<?php

namespace App\Filament\Widgets;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\CafeDashboardMetricsService;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformUserStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getHeading(): string
    {
        return __('filament.user_platform_overview');
    }

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $metrics = app(CafeDashboardMetricsService::class)->userOverview(
            DashboardFilterData::fromFilamentFilters($this->pageFilters),
            PlatformDashboardQuery::supportDownlineUserIds(),
        );

        return [
            Stat::make(__('filament.new_users'), number_format($metrics['new_users']))
                ->description(__('filament.registered_in_selected_range'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make(__('filament.active_users'), number_format($metrics['active_users']))
                ->description(__('filament.currently_active_account_status'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('filament.inactive_users'), number_format($metrics['inactive_users']))
                ->description(__('filament.currently_inactive_account_status'))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('gray'),

            Stat::make(__('filament.virtual_users'), number_format($metrics['virtual_users']))
                ->description(__('filament.total_marked_virtual_users'))
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('gray'),

            Stat::make(__('filament.active_user_products'), number_format($metrics['active_user_products']))
                ->description(__('filament.currently_active_product_orders'))
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),

            Stat::make(__('filament.expired_user_products'), number_format($metrics['expired_user_products']))
                ->description(__('filament.expired_in_selected_range'))
                ->descriptionIcon('heroicon-m-archive-box-x-mark')
                ->color('danger'),

            Stat::make(__('filament.recharge_wallet_balance'), PlatformDashboardQuery::money($metrics['recharge_wallet_balance']))
                ->description(__('filament.total_unused_recharge_balance'))
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make(__('filament.withdrawable_wallet_balance'), PlatformDashboardQuery::money($metrics['withdrawable_wallet_balance']))
                ->description(__('filament.total_user_claimable_balance'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}
