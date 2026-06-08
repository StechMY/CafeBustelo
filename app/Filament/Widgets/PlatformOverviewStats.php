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

    protected function getHeading(): string
    {
        return __('filament.finance_overview');
    }

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
            Stat::make(__('filament.approved_recharge'), PlatformDashboardQuery::money($approvedRecharge))
                ->description(__('filament.approved_deposits_in_selected_range'))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make(__('filament.approved_withdraw'), PlatformDashboardQuery::money($approvedWithdraw))
                ->description(__('filament.approved_withdrawals_in_selected_range'))
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('danger'),

            Stat::make(__('filament.revenue_cash_gap'), PlatformDashboardQuery::money($netRevenue))
                ->description(__('filament.approved_recharge_minus_approved_withdraw'))
                ->descriptionIcon($netRevenue >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($netRevenue >= 0 ? 'success' : 'danger'),

            Stat::make(__('filament.product_purchase_volume'), PlatformDashboardQuery::money($productPurchaseAmount))
                ->description(__('filament.recharge_wallet_spent_on_products'))
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make(__('filament.daily_income_paid'), PlatformDashboardQuery::money($dailyIncomePaid))
                ->description(__('filament.user_daily_income_claims'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make(__('filament.commissions_paid'), PlatformDashboardQuery::money($commissionsPaid))
                ->description(__('filament.b_c_d_commission_payouts'))
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),

            Stat::make(__('filament.bonus_paid'), PlatformDashboardQuery::money($bonusPaid))
                ->description(__('filament.invite_upgrade_bonus_claims'))
                ->descriptionIcon('heroicon-m-gift')
                ->color('gray'),

            Stat::make(__('filament.salary_subsidy_paid'), PlatformDashboardQuery::money($salaryPaid))
                ->description(__('filament.weekly_monthly_salary_claims'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('gray'),
        ];
    }
}