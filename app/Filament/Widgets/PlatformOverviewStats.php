<?php

namespace App\Filament\Widgets;

use App\Data\Dashboard\DashboardFilterData;
use App\Services\Dashboard\CafeDashboardMetricsService;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getHeading(): string
    {
        return __('filament.finance_overview');
    }

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $metrics = app(CafeDashboardMetricsService::class)->financeOverview(
            DashboardFilterData::fromFilamentFilters($this->pageFilters),
            PlatformDashboardQuery::supportDownlineUserIds(),
        );
        $netRevenue = $metrics['revenue_cash_gap'];

        return [
            Stat::make(__('filament.approved_recharge'), PlatformDashboardQuery::money($metrics['approved_recharge']))
                ->description(__('filament.approved_deposits_in_selected_range'))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make(__('filament.approved_withdraw'), PlatformDashboardQuery::money($metrics['approved_withdraw']))
                ->description(__('filament.approved_withdrawals_in_selected_range'))
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make(__('filament.revenue_cash_gap'), PlatformDashboardQuery::money($netRevenue))
                ->description(__('filament.approved_recharge_minus_approved_withdraw'))
                ->descriptionIcon($netRevenue >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netRevenue >= 0 ? 'success' : 'danger'),

            Stat::make(__('filament.product_purchase_volume'), PlatformDashboardQuery::money($metrics['product_purchase_volume']))
                ->description(__('filament.recharge_wallet_spent_on_products'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make(__('filament.daily_income_paid'), PlatformDashboardQuery::money($metrics['daily_income_paid']))
                ->description(__('filament.user_daily_income_claims'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make(__('filament.commissions_paid'), PlatformDashboardQuery::money($metrics['commissions_paid']))
                ->description(__('filament.b_c_d_commission_payouts'))
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),

            Stat::make(__('filament.bonus_paid'), PlatformDashboardQuery::money($metrics['bonus_paid']))
                ->description(__('filament.invite_upgrade_bonus_claims'))
                ->descriptionIcon('heroicon-m-gift')
                ->color('gray'),

            Stat::make(__('filament.salary_subsidy_paid'), PlatformDashboardQuery::money($metrics['salary_subsidy_paid']))
                ->description(__('filament.weekly_monthly_salary_claims'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
        ];
    }
}
