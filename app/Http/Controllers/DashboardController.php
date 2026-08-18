<?php

namespace App\Http\Controllers;

use App\Models\CheckInRecord;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\DashboardMarquee;
use App\Models\DashboardPopup;
use App\Models\DashboardSlide;
use App\Models\UserProduct;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const ACCOUNT_POLICY_SESSION_KEY = 'account_policy_agreed_v1';

    private const MAINTENANCE_NOTICE_SESSION_KEY = 'maintenance_notice_seen_2026_08_18';

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Mandatory Account Policy
        |--------------------------------------------------------------------------
        |
        | Show once per login session.
        |
        */
        $showAccountPolicy = ! (bool) $request->session()->get(
            self::ACCOUNT_POLICY_SESSION_KEY,
            false
        );

        // $maintenanceStartsAt = CarbonImmutable::create(2026, 8, 18, 0, 0, 0, 'Asia/Kuala_Lumpur');
        // $maintenanceEndsAt = CarbonImmutable::create(2026, 8, 19, 0, 0, 0, 'Asia/Kuala_Lumpur');
        // $maintenanceNow = CarbonImmutable::now('Asia/Kuala_Lumpur');

        $showMaintenanceNotice = ! $showAccountPolicy
            // && $maintenanceNow->greaterThanOrEqualTo($maintenanceStartsAt)
            // && $maintenanceNow->lessThan($maintenanceEndsAt)
            && ! $request->session()->has(self::MAINTENANCE_NOTICE_SESSION_KEY);

        if ($showMaintenanceNotice) {
            $request->session()->put(self::MAINTENANCE_NOTICE_SESSION_KEY, true);
        }

        $rechargeWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_RECHARGE)
            ->first();

        $withdrawableWallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $activeProducts = UserProduct::query()
            ->with('product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('purchased_at')
            ->limit(6)
            ->get();

        $todayDailyIncome = DailyIncomeClaim::query()
            ->where('user_id', $user->id)
            ->whereDate('claimed_at', today())
            ->sum('amount');

        $totalDailyIncome = DailyIncomeClaim::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        $totalCommission = CommissionRecord::query()
            ->where('earner_user_id', $user->id)
            ->where('status', 'paid')
            ->sum('amount');

        $directTeamCount = $user->referrals()
            ->where('status', 'active')
            ->count();

        $dashboardSlides = DashboardSlide::query()
            ->visible()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $dashboardMarquees = DashboardMarquee::query()
            ->visible()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        $dashboardPopup = DashboardPopup::query()
            ->visible()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Normal Dashboard Popup
        |--------------------------------------------------------------------------
        |
        | Do NOT process / mark normal popup as seen while the mandatory
        | account policy is covering the dashboard.
        |
        */
        $showDashboardPopup = false;

        if (! $showAccountPolicy && ! $showMaintenanceNotice && $dashboardPopup) {
            $sessionKey = 'dashboard_popup_seen_'.$dashboardPopup->id;

            if (
                $dashboardPopup->show_every_visit
                || ! $request->session()->has($sessionKey)
            ) {
                $showDashboardPopup = true;

                if (! $dashboardPopup->show_every_visit) {
                    $request->session()->put($sessionKey, true);
                }
            }
        }

        $todayCheckInRecord = CheckInRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('claim_date', today())
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Check-in Popup
        |--------------------------------------------------------------------------
        |
        | Mandatory account policy has higher priority.
        |
        */
        $shouldShowCheckInPopup =
            ! $showAccountPolicy
            && ! $showMaintenanceNotice
            && ! $showDashboardPopup
            && $user->status === 'active'
            && ! $todayCheckInRecord
            && $user->check_in_status;

        return view('dashboard.index', [
            'user' => $user,

            'wallets' => [
                'recharge' => $rechargeWallet?->balance ?? 0,
                'withdrawable' => $withdrawableWallet?->balance ?? 0,
            ],

            'stats' => [
                'today_daily_income' => $todayDailyIncome,
                'total_daily_income' => $totalDailyIncome,
                'total_commission' => $totalCommission,
                'direct_team_count' => $directTeamCount,
                'active_product_count' => $activeProducts->count(),
            ],

            'activeProducts' => $activeProducts,
            'dashboardSlides' => $dashboardSlides,
            'dashboardMarquees' => $dashboardMarquees,
            'dashboardPopup' => $dashboardPopup,
            'showDashboardPopup' => $showDashboardPopup,

            'todayCheckInRecord' => $todayCheckInRecord,
            'shouldShowCheckInPopup' => $shouldShowCheckInPopup,

            'showAccountPolicy' => $showAccountPolicy,
            'showMaintenanceNotice' => $showMaintenanceNotice,

            'currentVipLevel' => $user->currentVipLevel(),
        ]);
    }

    public function agreeAccountPolicy(Request $request): RedirectResponse
    {
        $request->session()->put(
            self::ACCOUNT_POLICY_SESSION_KEY,
            true
        );

        return redirect()->route('dashboard');
    }
}
