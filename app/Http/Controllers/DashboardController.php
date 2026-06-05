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
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

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

        $showDashboardPopup = false;

        if ($dashboardPopup) {
            $sessionKey = 'dashboard_popup_seen_' . $dashboardPopup->id;

            if ($dashboardPopup->show_every_visit || !session()->has($sessionKey)) {
                $showDashboardPopup = true;

                if (!$dashboardPopup->show_every_visit) {
                    session()->put($sessionKey, true);
                }
            }
        }

        $todayCheckInRecord = CheckInRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('claim_date', today())
            ->first();

        $shouldShowCheckInPopup = $user->status === 'active'
            && !$todayCheckInRecord && $user->check_in_status;

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
            'currentVipLevel' => $user->currentVipLevel(),
        ]);
    }
}