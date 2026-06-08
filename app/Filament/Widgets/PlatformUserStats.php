<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PlatformUserStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected function getHeading(): string
    {
        return __('filament.user_platform_overview');
    }

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->pageFilters;

        $newUsers = User::query()
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->count();

        $activeUsers = User::query()
            ->where('status', 'active')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->count();

        $inactiveUsers = User::query()
            ->where('status', 'inactive')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->count();

        $virtualUsers = User::query()
            ->where('is_virtual', true)
            ->count();

        $activeUserProducts = UserProduct::query()
            ->where('status', 'active')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();

        $expiredUserProducts = UserProduct::query()
            ->where('status', 'expired')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'expires_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();

        $rechargeWalletBalance = Wallet::query()
            ->where('type', 'recharge')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('balance');

        $withdrawableWalletBalance = Wallet::query()
            ->where('type', 'withdrawable')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('balance');

        return [
            Stat::make(__('filament.new_users'), number_format($newUsers))
                ->description(__('filament.registered_in_selected_range'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make(__('filament.active_users'), number_format($activeUsers))
                ->description(__('filament.currently_active_account_status'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('filament.inactive_users'), number_format($inactiveUsers))
                ->description(__('filament.currently_inactive_account_status'))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('gray'),

            Stat::make(__('filament.virtual_users'), number_format($virtualUsers))
                ->description(__('filament.total_marked_virtual_users'))
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('gray'),

            Stat::make(__('filament.active_user_products'), number_format($activeUserProducts))
                ->description(__('filament.currently_active_product_orders'))
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),

            Stat::make(__('filament.expired_user_products'), number_format($expiredUserProducts))
                ->description(__('filament.expired_in_selected_range'))
                ->descriptionIcon('heroicon-m-archive-box-x-mark')
                ->color('danger'),

            Stat::make(__('filament.recharge_wallet_balance'), PlatformDashboardQuery::money($rechargeWalletBalance))
                ->description(__('filament.total_unused_recharge_balance'))
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make(__('filament.withdrawable_wallet_balance'), PlatformDashboardQuery::money($withdrawableWalletBalance))
                ->description(__('filament.total_user_claimable_balance'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}