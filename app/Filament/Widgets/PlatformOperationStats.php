<?php

namespace App\Filament\Widgets;

use App\Models\BonusClaim;
use App\Models\CommissionRecord;
use App\Models\RechargeRequest;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Support\Filament\Dashboard\PlatformDashboardQuery;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PlatformOperationStats extends Widget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected string $view = 'filament.widgets.platform-operation-stats';

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getStats(): array
    {
        $filters = $this->pageFilters;

        $selectedStart = PlatformDashboardQuery::startDate($filters) ?? now()->startOfDay();
        $selectedEnd = PlatformDashboardQuery::endDate($filters) ?? now()->endOfDay();

        $todayStart = now()->startOfDay();
        $todayEnd = now();

        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay();

        $beforeYesterdayStart = now()->subDays(2)->startOfDay();
        $beforeYesterdayEnd = now()->subDays(2);

        $totalRecharge = $this->approvedRechargeAmount($filters);
        $totalWithdraw = $this->approvedWithdrawAmount($filters);
        $totalBalance = $this->totalWalletBalance($filters);
        $totalInvest = $this->productPurchaseAmount($filters);
        $totalOrders = $this->productOrderCount($filters);
        $totalMembers = $this->totalMembers($filters);
        $totalOrderCommission = $this->orderCommissionAmount($filters);

        $selectedRegistered = $this->registeredCount($filters, $selectedStart, $selectedEnd);
        $selectedFirstRechargeUsers = $this->firstRechargeUsers($filters, $selectedStart, $selectedEnd);
        $selectedTransferReferralUsers = $this->transferReferralUsers($filters, $selectedStart, $selectedEnd);
        $selectedValidMembers = $this->validMembers($filters);
        $selectedReferralRate = $selectedValidMembers > 0
            ? round(($selectedTransferReferralUsers / $selectedValidMembers) * 100, 2)
            : 0;

        $selectedUpgradeAmount = $this->bcdUpgradePurchaseAmount($filters, $selectedStart, $selectedEnd);
        $selectedRecharge = $this->approvedRechargeAmount($filters, $selectedStart, $selectedEnd);
        $selectedWithdraw = $this->approvedWithdrawAmount($filters, $selectedStart, $selectedEnd);

        $todayOrderCount = $this->productOrderCount($filters, $todayStart, $todayEnd);
        $todayRecharge = $this->approvedRechargeAmount($filters, $todayStart, $todayEnd);
        $todayFirstRechargeUsers = $this->firstRechargeUsers($filters, $todayStart, $todayEnd);
        $todayWithdraw = $this->approvedWithdrawAmount($filters, $todayStart, $todayEnd);
        $todayBonus = $this->bonusAmount($filters, $todayStart, $todayEnd);

        $yesterdayRecharge = $this->approvedRechargeAmount($filters, $yesterdayStart, $yesterdayEnd);
        $yesterdayFirstRechargeUsers = $this->firstRechargeUsers($filters, $yesterdayStart, $yesterdayEnd);
        $yesterdayWithdraw = $this->approvedWithdrawAmount($filters, $yesterdayStart, $yesterdayEnd);
        $yesterdayBonus = $this->bonusAmount($filters, $yesterdayStart, $yesterdayEnd);
        $yesterdayRegistered = $this->registeredCount($filters, $yesterdayStart, $yesterdayEnd);

        $beforeYesterdayRecharge = $this->approvedRechargeAmount($filters, $beforeYesterdayStart, $beforeYesterdayEnd);
        $beforeYesterdayWithdraw = $this->approvedWithdrawAmount($filters, $beforeYesterdayStart, $beforeYesterdayEnd);

        $pendingWithdrawAmount = WithdrawRequest::query()
            ->where('status', 'pending')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');

        $expiredProductMemberCount = UserProduct::query()
            ->where('status', 'expired')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->distinct('user_id')
            ->count('user_id');

        $validMembers = $this->validMembers($filters);
        $invalidMembers = max(0, $totalMembers - $validMembers);

        $invalidRecharge = RechargeRequest::query()
            ->where('status', 'approved')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->whereHas('user', fn(Builder $query) => $this->applyInvalidMemberScope($query))
            ->sum('amount');

        $invalidWithdraw = WithdrawRequest::query()
            ->where('status', 'approved')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->whereHas('user', fn(Builder $query) => $this->applyInvalidMemberScope($query))
            ->sum('amount');

        return [
            'selected' => [
                'registered' => $selectedRegistered,
                'first_recharge_users' => $selectedFirstRechargeUsers,
                'transfer_referral_users' => $selectedTransferReferralUsers,
                'valid_members' => $selectedValidMembers,
                'transfer_referral_rate' => $selectedReferralRate,
                'upgrade_amount' => $selectedUpgradeAmount,
                'recharge' => $selectedRecharge,
                'withdraw' => $selectedWithdraw,
            ],

            'overview' => [
                'total_recharge' => $totalRecharge,
                'total_balance' => $totalBalance,
                'total_invest' => $totalInvest,
                'total_withdraw' => $totalWithdraw,

                'total_members' => $totalMembers,
                'total_orders' => $totalOrders,
                'total_order_commission' => $totalOrderCommission,
                'today_orders' => $todayOrderCount,

                'today_recharge' => $todayRecharge,
                'today_first_recharge_users' => $todayFirstRechargeUsers,
                'today_withdraw' => $todayWithdraw,
                'today_bonus' => $todayBonus,

                'yesterday_recharge' => $yesterdayRecharge,
                'yesterday_first_recharge_users' => $yesterdayFirstRechargeUsers,
                'yesterday_withdraw' => $yesterdayWithdraw,
                'yesterday_bonus' => $yesterdayBonus,

                'before_yesterday_recharge' => $beforeYesterdayRecharge,
                'before_yesterday_withdraw' => $beforeYesterdayWithdraw,

                'pending_withdraw_amount' => $pendingWithdrawAmount,
                'expired_product_member_count' => $expiredProductMemberCount,
                'today_registered' => $selectedRegistered,
                'yesterday_registered' => $yesterdayRegistered,

                'valid_members' => $validMembers,
                'invalid_members' => $invalidMembers,

                'summary_balance' => $totalBalance,
                'invalid_members_total_recharge' => $invalidRecharge,
                'invalid_members_total_withdraw' => $invalidWithdraw,
            ],
        ];
    }

    private function approvedRechargeAmount(array $filters, ?Carbon $start = null, ?Carbon $end = null): float
    {
        return (float) RechargeRequest::query()
            ->where('status', 'approved')
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'reviewed_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');
    }

    private function approvedWithdrawAmount(array $filters, ?Carbon $start = null, ?Carbon $end = null): float
    {
        return (float) WithdrawRequest::query()
            ->where('status', 'approved')
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'reviewed_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');
    }

    private function productPurchaseAmount(array $filters, ?Carbon $start = null, ?Carbon $end = null): float
    {
        return (float) UserProduct::query()
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'purchased_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('price_snapshot');
    }

    private function productOrderCount(array $filters, ?Carbon $start = null, ?Carbon $end = null): int
    {
        return (int) UserProduct::query()
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'purchased_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->count();
    }

    private function orderCommissionAmount(array $filters): float
    {
        return (float) CommissionRecord::query()
            ->where('status', 'paid')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters, 'earner'))
            ->sum('amount');
    }

    private function totalWalletBalance(array $filters): float
    {
        return (float) Wallet::query()
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('balance');
    }

    private function totalMembers(array $filters): int
    {
        return (int) User::query()
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->count();
    }

    private function registeredCount(array $filters, Carbon $start, Carbon $end): int
    {
        return (int) User::query()
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'created_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->count();
    }

    private function validMembers(array $filters): int
    {
        return (int) User::query()
            ->where('status', 'active')
            ->whereHas('activeProducts')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->count();
    }

    private function firstRechargeUsers(array $filters, Carbon $start, Carbon $end): int
    {
        $subQuery = RechargeRequest::query()
            ->selectRaw('user_id, MIN(reviewed_at) as first_reviewed_at')
            ->where('status', 'approved')
            ->whereNotNull('reviewed_at')
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->groupBy('user_id');

        return (int) DB::query()
            ->fromSub($subQuery, 'first_recharges')
            ->where('first_reviewed_at', '>=', $start)
            ->where('first_reviewed_at', '<=', $end)
            ->count();
    }

    private function transferReferralUsers(array $filters, Carbon $start, Carbon $end): int
    {
        return (int) User::query()
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'created_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyDirectUserVirtualFilter($query, $filters))
            ->where(fn(Builder $query) => $this->applyIndirectDownlineScope($query))
            ->count();
    }

    private function bcdUpgradePurchaseAmount(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) UserProduct::query()
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'purchased_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->whereHas('user', fn(Builder $query) => $this->applyIndirectDownlineScope($query))
            ->whereHas('product', function (Builder $query): void {
                $query->whereRaw('LOWER(code) <> ?', ['t1']);
            })
            ->sum('price_snapshot');
    }

    private function bonusAmount(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) BonusClaim::query()
            ->tap(fn(Builder $query) => $this->applyOptionalDateRange($query, 'claimed_at', $start, $end))
            ->tap(fn(Builder $query) => PlatformDashboardQuery::applyUserVirtualFilter($query, $filters))
            ->sum('amount');
    }

    private function applyOptionalDateRange(
        Builder $query,
        string $column,
        ?Carbon $start,
        ?Carbon $end,
    ): Builder {
        return $query
            ->when($start, fn(Builder $query): Builder => $query->where($column, '>=', $start))
            ->when($end, fn(Builder $query): Builder => $query->where($column, '<=', $end));
    }

    private function applyIndirectDownlineScope(Builder $query): Builder
    {
        return $query
            ->whereNotNull('referrer_id')
            ->whereHas('referrer', function (Builder $query): void {
                $query->whereNotNull('referrer_id');
            });
    }

    private function applyInvalidMemberScope(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('status', '!=', 'active')
                ->orWhereDoesntHave('activeProducts');
        });
    }
}