<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckInRecord;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\DashboardMarquee;
use App\Models\DashboardPopup;
use App\Models\DashboardSlide;
use App\Models\UserProduct;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use App\Support\Api\DisplayValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $rechargeWallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_RECHARGE)->first();
        $withdrawableWallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_WITHDRAWABLE)->first();

        $activeProducts = UserProduct::query()
            ->with('product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('purchased_at')
            ->limit(6)
            ->get();

        $todayCheckInRecord = CheckInRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('claim_date', today())
            ->first();

        $dashboardPopup = DashboardPopup::query()
            ->visible()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->first();
        $todayDailyIncome = DailyIncomeClaim::query()->where('user_id', $user->id)->whereDate('claimed_at', today())->sum('amount');
        $totalDailyIncome = DailyIncomeClaim::query()->where('user_id', $user->id)->sum('amount');
        $totalCommission = CommissionRecord::query()->where('earner_user_id', $user->id)->where('status', 'paid')->sum('amount');
        $activeTeamSize = $user->activeTeamSize();
        $realCurrentVipLevel = VipLevel::query()
            ->where('status', 'active')
            ->where('required_accumulated_recharge', '<=', $user->accumulated_recharge)
            ->where('required_active_team_size', '<=', $activeTeamSize)
            ->orderByDesc('level')
            ->first();
        $vipLevelValue = DisplayValue::resolve($user, 'display_vip_level', $realCurrentVipLevel?->level ?? 1);

        return ApiResponse::success([
            'user' => ApiFormatter::user($user),
            'wallets' => [
                'recharge' => ApiFormatter::wallet($rechargeWallet, Wallet::TYPE_RECHARGE),
                'withdrawable' => ApiFormatter::wallet($withdrawableWallet, Wallet::TYPE_WITHDRAWABLE),
            ],
            'stats' => [
                ...DisplayValue::moneyFields($user, 'today_daily_income', 'display_income_today', $todayDailyIncome),
                ...DisplayValue::moneyFields($user, 'total_daily_income', 'display_daily_income_total', $totalDailyIncome),
                ...DisplayValue::moneyFields($user, 'total_commission', 'display_team_income', $totalCommission),
                'direct_team_count' => $user->referrals()->where('status', 'active')->count(),
                ...DisplayValue::integerFields($user, 'active_team_count', 'display_active_team_count', $activeTeamSize),
                'active_product_count' => $activeProducts->count(),
            ],
            'account_summary' => [
                ...DisplayValue::moneyFields($user, 'accumulated_recharge', 'display_accumulated_recharge', $user->accumulated_recharge),
                'current_vip_level' => ApiFormatter::vipLevel($realCurrentVipLevel),
                'display_current_vip_level' => ApiFormatter::vipLevel($user->currentVipLevel() ?: $realCurrentVipLevel),
                'is_vip_level_overridden' => $vipLevelValue['is_overridden'],
            ],
            'active_products' => $activeProducts->map(fn (UserProduct $item): array => ApiFormatter::userProduct($item))->values(),
            'slides' => DashboardSlide::query()->visible()->orderBy('sort_order')->orderByDesc('id')->get()->map(fn ($slide): array => [
                'id' => (int) $slide->id,
                'title' => $slide->title,
                'description' => $slide->description,
                'image_url' => $slide->image_url,
                'button_text' => $slide->button_text,
                'button_url' => $slide->button_url,
            ])->values(),
            'marquees' => DashboardMarquee::query()->visible()->orderBy('sort_order')->orderByDesc('id')->get()->map(fn ($marquee): array => [
                'id' => (int) $marquee->id,
                'title' => $marquee->title,
                'message' => $marquee->message,
                'type' => $marquee->type,
            ])->values(),
            'popup' => $dashboardPopup ? [
                'id' => (int) $dashboardPopup->id,
                'title' => $dashboardPopup->title,
                'description' => $dashboardPopup->description,
                'image_url' => $dashboardPopup->image_url,
                'button_text' => $dashboardPopup->button_text,
                'button_url' => $dashboardPopup->button_url,
                'show_every_visit' => (bool) $dashboardPopup->show_every_visit,
            ] : null,
            'today_check_in_record' => $todayCheckInRecord ? ApiFormatter::checkInRecord($todayCheckInRecord) : null,
            'should_show_check_in_popup' => $user->status === 'active' && ! $todayCheckInRecord && (bool) $user->check_in_status,
            'current_vip_level' => ApiFormatter::vipLevel($realCurrentVipLevel),
            'display_current_vip_level' => ApiFormatter::vipLevel($user->currentVipLevel() ?: $realCurrentVipLevel),
            'is_vip_level_overridden' => $vipLevelValue['is_overridden'],
        ]);
    }
}
