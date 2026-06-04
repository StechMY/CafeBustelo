<?php

namespace App\Filament\Widgets;

use App\Models\BonusClaim;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\UserProduct;
use App\Models\WithdrawRequest;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PlatformOverviewStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Finance Overview';

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $filters = $this->pageFilters;

        $approvedRecharge = RechargeRequest::query()
            ->where('status', 'approved')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'reviewed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $approvedWithdraw = WithdrawRequest::query()
            ->where('status', 'approved')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'reviewed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $netRevenue = (float) $approvedRecharge - (float) $approvedWithdraw;

        $productPurchaseAmount = UserProduct::query()
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'purchased_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('price_snapshot');

        $dailyIncomePaid = DailyIncomeClaim::query()
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'claimed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $commissionsPaid = CommissionRecord::query()
            ->where('status', 'paid')
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'created_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters, 'earner'))
            ->sum('amount');

        $bonusPaid = BonusClaim::query()
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'claimed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $salaryPaid = SalaryClaim::query()
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyDateRange($query, $filters, 'claimed_at'))
            ->tap(fn (Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('total_amount');

        return [
            Stat::make('Approved Recharge', PlatformDashboardQuery::money($approvedRecharge))
                ->description('Approved deposits in selected range')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Approved Withdraw', PlatformDashboardQuery::money($approvedWithdraw))
                ->description('Approved withdrawals in selected range')
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make('Revenue / Cash Gap', PlatformDashboardQuery::money($netRevenue))
                ->description('Approved recharge - approved withdraw')
                ->descriptionIcon($netRevenue >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netRevenue >= 0 ? 'success' : 'danger'),

            Stat::make('Product Purchase Volume', PlatformDashboardQuery::money($productPurchaseAmount))
                ->description('Recharge wallet spent on products')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make('Daily Income Paid', PlatformDashboardQuery::money($dailyIncomePaid))
                ->description('User daily income claims')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Commissions Paid', PlatformDashboardQuery::money($commissionsPaid))
                ->description('B/C/D commission payouts')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),

            Stat::make('Bonus Paid', PlatformDashboardQuery::money($bonusPaid))
                ->description('Invite + upgrade bonus claims')
                ->descriptionIcon('heroicon-m-gift')
                ->color('gray'),

            Stat::make('Salary + Subsidy Paid', PlatformDashboardQuery::money($salaryPaid))
                ->description('Weekly / monthly salary claims')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
        ];
    }
}