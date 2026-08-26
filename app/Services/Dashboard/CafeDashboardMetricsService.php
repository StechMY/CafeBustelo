<?php

namespace App\Services\Dashboard;

use App\Data\Dashboard\DashboardFilterData;
use App\Models\BonusClaim;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\User;
use App\Models\UserProduct;
use App\Models\Wallet;
use App\Models\WithdrawRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CafeDashboardMetricsService
{
    public function dashboard(DashboardFilterData $filters): array
    {
        $operationSelected = $this->operationSelected($filters);
        $operationOverview = $this->operationOverview($filters);
        $financeOverview = $this->financeOverview($filters);

        $common = [
            'cash_in' => $financeOverview['approved_recharge'],
            'cash_out' => $financeOverview['approved_withdraw'],
            'net_cash_flow' => $financeOverview['revenue_cash_gap'],
            'new_users' => $operationSelected['registered'],
            'total_users' => $operationOverview['total_members'],
            'order_count' => $this->productOrderCount(
                $filters,
                $filters->dateFrom,
                $filters->dateTo,
            ),
        ];

        return [
            'common' => $common,
            'sections' => [
                'operation_selected' => $operationSelected,
                'operation_overview' => $operationOverview,
                'finance_overview' => $financeOverview,
                'request_overview' => $this->requestOverview($filters),
                'user_overview' => $this->userOverview($filters),
                'finance_chart' => $this->financeChart($filters),
            ],
        ];
    }

    public function operationMetrics(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        return [
            'selected' => $this->operationSelected($filters, $visibleUserIds),
            'overview' => $this->operationOverview($filters, $visibleUserIds),
        ];
    }

    public function operationSelected(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        $registered = $this->registeredCount(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );
        $firstRechargeUsers = $this->firstRechargeUsers(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );
        $transferReferralUsers = $this->transferReferralUsers(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );
        $validMembers = $this->validMembers($filters, $visibleUserIds);

        return [
            'registered' => $registered,
            'first_recharge_users' => $firstRechargeUsers,
            'transfer_referral_users' => $transferReferralUsers,
            'valid_members' => $validMembers,
            'transfer_referral_rate' => $validMembers > 0
                ? round(($transferReferralUsers / $validMembers) * 100, 2)
                : 0.0,
            'upgrade_amount' => $this->money($this->bcdUpgradePurchaseAmount(
                $filters,
                $filters->dateFrom,
                $filters->dateTo,
                $visibleUserIds,
            )),
            'recharge' => $this->money($this->approvedRechargeAmount(
                $filters,
                $filters->dateFrom,
                $filters->dateTo,
                $visibleUserIds,
            )),
            'withdraw' => $this->money($this->approvedWithdrawAmount(
                $filters,
                $filters->dateFrom,
                $filters->dateTo,
                $visibleUserIds,
            )),
        ];
    }

    public function operationOverview(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        $now = CarbonImmutable::now((string) config('app.timezone'));
        $todayStart = $now->startOfDay();
        $yesterdayStart = $now->subDay()->startOfDay();
        $yesterdayEnd = $now->subDay();
        $beforeYesterdayStart = $now->subDays(2)->startOfDay();
        $beforeYesterdayEnd = $now->subDays(2);

        $totalRecharge = $this->approvedRechargeAmount($filters, visibleUserIds: $visibleUserIds);
        $totalWithdraw = $this->approvedWithdrawAmount($filters, visibleUserIds: $visibleUserIds);
        $totalBalance = $this->totalWalletBalance($filters, $visibleUserIds);
        $totalInvest = $this->productPurchaseAmount($filters, visibleUserIds: $visibleUserIds);
        $totalOrders = $this->productOrderCount($filters, visibleUserIds: $visibleUserIds);
        $totalMembers = $this->totalMembers($filters, $visibleUserIds);
        $totalOrderCommission = $this->orderCommissionAmount($filters, $visibleUserIds);

        $selectedRegistered = $this->registeredCount(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );
        $validMembers = $this->validMembers($filters, $visibleUserIds);
        $invalidMembers = max(0, $totalMembers - $validMembers);

        $pendingWithdrawAmount = WithdrawRequest::query()
            ->where('status', 'pending')
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('amount');

        $expiredProductMemberCount = UserProduct::query()
            ->where('status', 'expired')
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->distinct('user_id')
            ->count('user_id');

        $invalidRecharge = RechargeRequest::query()
            ->where('status', 'approved')
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->whereHas('user', fn (Builder $query) => $this->applyInvalidMemberScope($query))
            ->sum('amount');

        $invalidWithdraw = WithdrawRequest::query()
            ->paid()
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->whereHas('user', fn (Builder $query) => $this->applyInvalidMemberScope($query))
            ->sum('amount');

        return [
            'total_recharge' => $this->money($totalRecharge),
            'total_balance' => $this->money($totalBalance),
            'total_invest' => $this->money($totalInvest),
            'total_withdraw' => $this->money($totalWithdraw),
            'total_members' => $totalMembers,
            'total_orders' => $totalOrders,
            'total_order_commission' => $this->money($totalOrderCommission),
            'today_orders' => $this->productOrderCount(
                $filters,
                $todayStart,
                $now,
                $visibleUserIds,
            ),
            'today_recharge' => $this->money($this->approvedRechargeAmount(
                $filters,
                $todayStart,
                $now,
                $visibleUserIds,
            )),
            'today_first_recharge_users' => $this->firstRechargeUsers(
                $filters,
                $todayStart,
                $now,
                $visibleUserIds,
            ),
            'today_withdraw' => $this->money($this->approvedWithdrawAmount(
                $filters,
                $todayStart,
                $now,
                $visibleUserIds,
            )),
            'today_bonus' => $this->money($this->bonusAmount(
                $filters,
                $todayStart,
                $now,
                $visibleUserIds,
            )),
            'yesterday_recharge' => $this->money($this->approvedRechargeAmount(
                $filters,
                $yesterdayStart,
                $yesterdayEnd,
                $visibleUserIds,
            )),
            'yesterday_first_recharge_users' => $this->firstRechargeUsers(
                $filters,
                $yesterdayStart,
                $yesterdayEnd,
                $visibleUserIds,
            ),
            'yesterday_withdraw' => $this->money($this->approvedWithdrawAmount(
                $filters,
                $yesterdayStart,
                $yesterdayEnd,
                $visibleUserIds,
            )),
            'yesterday_bonus' => $this->money($this->bonusAmount(
                $filters,
                $yesterdayStart,
                $yesterdayEnd,
                $visibleUserIds,
            )),
            'before_yesterday_recharge' => $this->money($this->approvedRechargeAmount(
                $filters,
                $beforeYesterdayStart,
                $beforeYesterdayEnd,
                $visibleUserIds,
            )),
            'before_yesterday_withdraw' => $this->money($this->approvedWithdrawAmount(
                $filters,
                $beforeYesterdayStart,
                $beforeYesterdayEnd,
                $visibleUserIds,
            )),
            'pending_withdraw_amount' => $this->money($pendingWithdrawAmount),
            'expired_product_member_count' => $expiredProductMemberCount,
            // Legacy dashboard compatibility: this label contains the selected-range count.
            'today_registered' => $selectedRegistered,
            'yesterday_registered' => $this->registeredCount(
                $filters,
                $yesterdayStart,
                $yesterdayEnd,
                $visibleUserIds,
            ),
            'valid_members' => $validMembers,
            'invalid_members' => $invalidMembers,
            'summary_balance' => $this->money($totalBalance),
            'invalid_members_total_recharge' => $this->money($invalidRecharge),
            'invalid_members_total_withdraw' => $this->money($invalidWithdraw),
        ];
    }

    public function financeOverview(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        $approvedRecharge = $this->approvedRechargeAmount(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );
        $approvedWithdraw = $this->approvedWithdrawAmount(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );

        $productPurchaseAmount = UserProduct::query()
            ->whereBetween('purchased_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('price_snapshot');

        $dailyIncomePaid = DailyIncomeClaim::query()
            ->whereBetween('claimed_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('amount');

        $commissionsPaid = CommissionRecord::query()
            ->where('status', 'paid')
            ->whereBetween('created_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'earner',
                $visibleUserIds,
            ))
            ->sum('amount');

        $bonusPaid = BonusClaim::query()
            ->whereBetween('claimed_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('amount');

        $salaryPaid = SalaryClaim::query()
            ->whereBetween('claimed_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('total_amount');

        return [
            'approved_recharge' => $this->money($approvedRecharge),
            'approved_withdraw' => $this->money($approvedWithdraw),
            'revenue_cash_gap' => $this->money($approvedRecharge - $approvedWithdraw),
            'product_purchase_volume' => $this->money($productPurchaseAmount),
            'daily_income_paid' => $this->money($dailyIncomePaid),
            'commissions_paid' => $this->money($commissionsPaid),
            'bonus_paid' => $this->money($bonusPaid),
            'salary_subsidy_paid' => $this->money($salaryPaid),
        ];
    }

    public function requestOverview(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        $pendingRecharge = RechargeRequest::query()
            ->where('status', 'pending')
            ->whereBetween('created_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->selectRaw('COUNT(*) AS aggregate_count, COALESCE(SUM(amount), 0) AS aggregate_amount')
            ->toBase()
            ->first();

        $pendingWithdraw = WithdrawRequest::query()
            ->where('status', 'pending')
            ->whereBetween('created_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->selectRaw('COUNT(*) AS aggregate_count, COALESCE(SUM(amount), 0) AS aggregate_amount')
            ->toBase()
            ->first();

        $rejectedRechargeCount = RechargeRequest::query()
            ->where('status', 'rejected')
            ->whereBetween('reviewed_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->count();

        $rejectedWithdrawCount = WithdrawRequest::query()
            ->where('status', 'rejected')
            ->whereBetween('reviewed_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->count();

        return [
            'pending_recharge_count' => (int) ($pendingRecharge->aggregate_count ?? 0),
            'pending_recharge_amount' => $this->money($pendingRecharge->aggregate_amount ?? 0),
            'pending_withdraw_count' => (int) ($pendingWithdraw->aggregate_count ?? 0),
            'pending_withdraw_amount' => $this->money($pendingWithdraw->aggregate_amount ?? 0),
            'rejected_recharge_count' => $rejectedRechargeCount,
            'rejected_withdraw_count' => $rejectedWithdrawCount,
        ];
    }

    public function userOverview(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        $newUsers = $this->registeredCount(
            $filters,
            $filters->dateFrom,
            $filters->dateTo,
            $visibleUserIds,
        );
        $activeUsers = User::query()
            ->where('status', 'active')
            ->tap(fn (Builder $query) => $this->applyDirectUserScope(
                $query,
                $filters->virtualMode,
                $visibleUserIds,
            ))
            ->count();
        $inactiveUsers = User::query()
            ->where('status', 'inactive')
            ->tap(fn (Builder $query) => $this->applyDirectUserScope(
                $query,
                $filters->virtualMode,
                $visibleUserIds,
            ))
            ->count();

        // Legacy dashboard compatibility: virtual_users ignores the selected virtual mode.
        $virtualUsers = User::query()
            ->where('is_virtual', true)
            ->tap(fn (Builder $query) => $this->applyVisibleUserScope($query, $visibleUserIds))
            ->count();

        $activeUserProducts = UserProduct::query()
            ->where('status', 'active')
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->count();
        $expiredUserProducts = UserProduct::query()
            ->where('status', 'expired')
            ->whereBetween('expires_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->count();
        $rechargeWalletBalance = Wallet::query()
            ->where('type', Wallet::TYPE_RECHARGE)
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('balance');
        $withdrawableWalletBalance = Wallet::query()
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('balance');

        return [
            'new_users' => $newUsers,
            'active_users' => $activeUsers,
            'inactive_users' => $inactiveUsers,
            'virtual_users' => $virtualUsers,
            'active_user_products' => $activeUserProducts,
            'expired_user_products' => $expiredUserProducts,
            'recharge_wallet_balance' => $this->money($rechargeWalletBalance),
            'withdrawable_wallet_balance' => $this->money($withdrawableWalletBalance),
        ];
    }

    public function financeChart(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): array {
        $rechargeByDate = RechargeRequest::query()
            ->where('status', 'approved')
            ->whereBetween('reviewed_at', [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->selectRaw('DATE(reviewed_at) AS aggregate_date, SUM(amount) AS aggregate_amount')
            ->groupByRaw('DATE(reviewed_at)')
            ->pluck('aggregate_amount', 'aggregate_date')
            ->map(fn ($amount): float => $this->money($amount))
            ->all();

        $withdrawByDate = WithdrawRequest::query()
            ->paid()
            ->whereBetween(DB::raw('COALESCE(gateway_completed_at, reviewed_at)'), [$filters->dateFrom, $filters->dateTo])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->selectRaw('DATE(COALESCE(gateway_completed_at, reviewed_at)) AS aggregate_date, SUM(amount) AS aggregate_amount')
            ->groupByRaw('DATE(COALESCE(gateway_completed_at, reviewed_at))')
            ->pluck('aggregate_amount', 'aggregate_date')
            ->map(fn ($amount): float => $this->money($amount))
            ->all();

        $labels = [];
        $approvedRecharge = [];
        $approvedWithdraw = [];
        $net = [];

        for (
            $date = $filters->dateFrom->startOfDay();
            $date->lte($filters->dateTo);
            $date = $date->addDay()
        ) {
            $label = $date->toDateString();
            $recharge = $rechargeByDate[$label] ?? 0.0;
            $withdraw = $withdrawByDate[$label] ?? 0.0;

            $labels[] = $label;
            $approvedRecharge[] = $recharge;
            $approvedWithdraw[] = $withdraw;
            $net[] = $this->money($recharge - $withdraw);
        }

        return [
            'labels' => $labels,
            'approved_recharge' => $approvedRecharge,
            'approved_withdraw' => $approvedWithdraw,
            'net' => $net,
        ];
    }

    private function approvedRechargeAmount(
        DashboardFilterData $filters,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?array $visibleUserIds = null,
    ): float {
        return (float) RechargeRequest::query()
            ->where('status', 'approved')
            ->tap(fn (Builder $query) => $this->applyOptionalDateRange(
                $query,
                'reviewed_at',
                $start,
                $end,
            ))
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('amount');
    }

    private function approvedWithdrawAmount(
        DashboardFilterData $filters,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?array $visibleUserIds = null,
    ): float {
        return (float) WithdrawRequest::query()
            ->paid()
            ->when($start, fn (Builder $query): Builder => $query->whereRaw(
                'COALESCE(gateway_completed_at, reviewed_at) >= ?',
                [$start],
            ))
            ->when($end, fn (Builder $query): Builder => $query->whereRaw(
                'COALESCE(gateway_completed_at, reviewed_at) <= ?',
                [$end],
            ))
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('amount');
    }

    private function productPurchaseAmount(
        DashboardFilterData $filters,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?array $visibleUserIds = null,
    ): float {
        return (float) UserProduct::query()
            ->tap(fn (Builder $query) => $this->applyOptionalDateRange(
                $query,
                'purchased_at',
                $start,
                $end,
            ))
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('price_snapshot');
    }

    private function productOrderCount(
        DashboardFilterData $filters,
        ?CarbonInterface $start = null,
        ?CarbonInterface $end = null,
        ?array $visibleUserIds = null,
    ): int {
        return UserProduct::query()
            ->tap(fn (Builder $query) => $this->applyOptionalDateRange(
                $query,
                'purchased_at',
                $start,
                $end,
            ))
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->count();
    }

    private function orderCommissionAmount(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): float {
        return (float) CommissionRecord::query()
            ->where('status', 'paid')
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'earner',
                $visibleUserIds,
            ))
            ->sum('amount');
    }

    private function totalWalletBalance(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): float {
        return (float) Wallet::query()
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('balance');
    }

    private function totalMembers(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): int {
        return User::query()
            ->tap(fn (Builder $query) => $this->applyDirectUserScope(
                $query,
                $filters->virtualMode,
                $visibleUserIds,
            ))
            ->count();
    }

    private function registeredCount(
        DashboardFilterData $filters,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $visibleUserIds = null,
    ): int {
        return User::query()
            ->whereBetween('created_at', [$start, $end])
            ->tap(fn (Builder $query) => $this->applyDirectUserScope(
                $query,
                $filters->virtualMode,
                $visibleUserIds,
            ))
            ->count();
    }

    private function validMembers(
        DashboardFilterData $filters,
        ?array $visibleUserIds = null,
    ): int {
        return User::query()
            ->where('status', 'active')
            ->whereHas('activeProducts')
            ->tap(fn (Builder $query) => $this->applyDirectUserScope(
                $query,
                $filters->virtualMode,
                $visibleUserIds,
            ))
            ->count();
    }

    private function firstRechargeUsers(
        DashboardFilterData $filters,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $visibleUserIds = null,
    ): int {
        $subQuery = RechargeRequest::query()
            ->selectRaw('user_id, MIN(reviewed_at) AS first_reviewed_at')
            ->where('status', 'approved')
            ->whereNotNull('reviewed_at')
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->groupBy('user_id');

        return DB::query()
            ->fromSub($subQuery, 'first_recharges')
            ->whereBetween('first_reviewed_at', [$start, $end])
            ->count();
    }

    private function transferReferralUsers(
        DashboardFilterData $filters,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $visibleUserIds = null,
    ): int {
        $firstPurchaseSubQuery = UserProduct::query()
            ->selectRaw('user_id, MIN(purchased_at) AS first_purchased_at')
            ->whereIn('status', ['active', 'expired', 'completed'])
            ->whereNotNull('purchased_at')
            ->groupBy('user_id');

        return User::query()
            ->joinSub($firstPurchaseSubQuery, 'first_product_purchases', function ($join): void {
                $join->on('first_product_purchases.user_id', '=', 'users.id');
            })
            ->tap(fn (Builder $query) => $this->applyDirectUserScope(
                $query,
                $filters->virtualMode,
                $visibleUserIds,
            ))
            ->whereNotNull('users.referrer_id')
            ->whereBetween('first_product_purchases.first_purchased_at', [$start, $end])
            ->count('users.id');
    }

    private function bcdUpgradePurchaseAmount(
        DashboardFilterData $filters,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $visibleUserIds = null,
    ): float {
        return (float) UserProduct::query()
            ->whereBetween('purchased_at', [$start, $end])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->whereHas('user', fn (Builder $query) => $this->applyIndirectDownlineScope($query))
            ->whereHas('product', function (Builder $query): void {
                $query->whereRaw('LOWER(code) <> ?', ['t1']);
            })
            ->sum('price_snapshot');
    }

    private function bonusAmount(
        DashboardFilterData $filters,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $visibleUserIds = null,
    ): float {
        return (float) BonusClaim::query()
            ->whereBetween('claimed_at', [$start, $end])
            ->tap(fn (Builder $query) => $this->applyRelatedUserScope(
                $query,
                $filters->virtualMode,
                'user',
                $visibleUserIds,
            ))
            ->sum('amount');
    }

    private function applyOptionalDateRange(
        Builder $query,
        string $column,
        ?CarbonInterface $start,
        ?CarbonInterface $end,
    ): Builder {
        return $query
            ->when($start, fn (Builder $query): Builder => $query->where($column, '>=', $start))
            ->when($end, fn (Builder $query): Builder => $query->where($column, '<=', $end));
    }

    private function applyDirectUserScope(
        Builder $query,
        string $virtualMode,
        ?array $visibleUserIds,
    ): Builder {
        $this->applyVisibleUserScope($query, $visibleUserIds);

        return match ($virtualMode) {
            'include_all' => $query,
            'only_virtual' => $query->where('users.is_virtual', true),
            default => $query->where('users.is_virtual', false),
        };
    }

    private function applyVisibleUserScope(Builder $query, ?array $visibleUserIds): Builder
    {
        if ($visibleUserIds === null) {
            return $query;
        }

        return $visibleUserIds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('users.id', $visibleUserIds);
    }

    private function applyRelatedUserScope(
        Builder $query,
        string $virtualMode,
        string $relation,
        ?array $visibleUserIds,
    ): Builder {
        if ($virtualMode === 'include_all' && $visibleUserIds === null) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $query) use (
            $virtualMode,
            $visibleUserIds,
        ): void {
            $this->applyDirectUserScope($query, $virtualMode, $visibleUserIds);
        });
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

    private function money(int|float|string|null $amount): float
    {
        return round((float) ($amount ?? 0), 2);
    }
}
