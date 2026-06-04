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

    protected ?string $heading = 'User & Platform Overview';

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
            Stat::make('New Users', number_format($newUsers))
                ->description('Registered in selected range')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Active Users', number_format($activeUsers))
                ->description('Currently active account status')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Inactive Users', number_format($inactiveUsers))
                ->description('Currently inactive account status')
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('gray'),

            Stat::make('Virtual Users', number_format($virtualUsers))
                ->description('Total marked virtual users')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('gray'),

            Stat::make('Active User Products', number_format($activeUserProducts))
                ->description('Currently active product orders')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),

            Stat::make('Expired User Products', number_format($expiredUserProducts))
                ->description('Expired in selected range')
                ->descriptionIcon('heroicon-m-archive-box-x-mark')
                ->color('danger'),

            Stat::make('Recharge Wallet Balance', PlatformDashboardQuery::money($rechargeWalletBalance))
                ->description('Total unused recharge balance')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Withdrawable Wallet Balance', PlatformDashboardQuery::money($withdrawableWalletBalance))
                ->description('Total user claimable balance')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}