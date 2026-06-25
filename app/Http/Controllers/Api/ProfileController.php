<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProfileAvatarRequest;
use App\Http\Requests\Api\ProfileUpdateRequest;
use App\Models\BonusCode;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\SvipPackage;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use App\Services\SalaryService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use App\Support\Api\DisplayValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request, SalaryService $salaryService): JsonResponse
    {
        $user = $request->user();
        $rechargeWallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_RECHARGE)->first();
        $withdrawableWallet = Wallet::query()->where('user_id', $user->id)->where('type', Wallet::TYPE_WITHDRAWABLE)->first();
        $activeTeamSize = $user->activeTeamSize();
        $realCurrentVipLevel = VipLevel::query()
            ->where('status', 'active')
            ->where('required_accumulated_recharge', '<=', $user->accumulated_recharge)
            ->where('required_active_team_size', '<=', $activeTeamSize)
            ->orderByDesc('level')
            ->first();
        $displayCurrentVipLevel = $salaryService->currentVipLevel($user) ?: $realCurrentVipLevel;

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $totalEarning = WalletTransaction::query()->where('user_id', $user->id)->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)->where('direction', WalletTransaction::DIRECTION_CREDIT)->sum('amount');
        $totalWithdrawals = WithdrawRequest::query()->where('user_id', $user->id)->where('status', 'approved')->sum('net_amount');
        $teamIncome = CommissionRecord::query()->where('earner_user_id', $user->id)->where('status', 'paid')->sum('amount');
        $incomeToday = WalletTransaction::query()->where('user_id', $user->id)->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)->where('direction', WalletTransaction::DIRECTION_CREDIT)->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
        $yesterdayEarnings = WalletTransaction::query()->where('user_id', $user->id)->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)->where('direction', WalletTransaction::DIRECTION_CREDIT)->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('amount');
        $dailyIncomeTotal = DailyIncomeClaim::query()->where('user_id', $user->id)->sum('amount');
        $salaryIncomeTotal = SalaryClaim::query()->where('user_id', $user->id)->sum('total_amount');

        $stats = [
            'recharge_wallet_balance' => ApiFormatter::money($rechargeWallet?->balance),
            'withdrawable_wallet_balance' => ApiFormatter::money($withdrawableWallet?->balance),
            ...DisplayValue::moneyFields($user, 'accumulated_recharge', 'display_accumulated_recharge', $user->accumulated_recharge),
            ...DisplayValue::moneyFields($user, 'total_earning', 'display_total_earning', $totalEarning),
            ...DisplayValue::moneyFields($user, 'total_withdrawals', 'display_total_withdrawals', $totalWithdrawals),
            'pending_withdrawals' => ApiFormatter::money(WithdrawRequest::query()->where('user_id', $user->id)->where('status', 'pending')->sum('amount')),
            ...DisplayValue::moneyFields($user, 'team_income', 'display_team_income', $teamIncome),
            ...DisplayValue::moneyFields($user, 'income_today', 'display_income_today', $incomeToday),
            ...DisplayValue::moneyFields($user, 'yesterday_earnings', 'display_yesterday_earnings', $yesterdayEarnings),
            ...DisplayValue::moneyFields($user, 'daily_income_total', 'display_daily_income_total', $dailyIncomeTotal),
            ...DisplayValue::moneyFields($user, 'salary_income_total', 'display_salary_income_total', $salaryIncomeTotal),
            'approved_recharge_total' => ApiFormatter::money(RechargeRequest::query()->where('user_id', $user->id)->where('status', 'approved')->sum('amount')),
            'unused_bonus_count' => BonusCode::query()->where('owner_user_id', $user->id)->where('status', BonusCode::STATUS_UNUSED)->count(),
        ];

        $vipLevelValue = DisplayValue::resolve($user, 'display_vip_level', $realCurrentVipLevel?->level ?? 1);
        $displayVipLevelNo = (int) $vipLevelValue['display'];
        $activeTeamValue = DisplayValue::integerFields($user, 'active_team_size', 'display_active_team_count', $activeTeamSize);

        return ApiResponse::success([
            'user' => ApiFormatter::user($user),
            'wallets' => [
                'recharge' => ApiFormatter::wallet($rechargeWallet, Wallet::TYPE_RECHARGE),
                'withdrawable' => ApiFormatter::wallet($withdrawableWallet, Wallet::TYPE_WITHDRAWABLE),
            ],
            'stats' => $stats,
            'current_vip_level' => ApiFormatter::vipLevel($realCurrentVipLevel),
            'display_current_vip_level' => ApiFormatter::vipLevel($displayCurrentVipLevel),
            'is_vip_level_overridden' => $vipLevelValue['is_overridden'],
            'next_vip_level' => ApiFormatter::vipLevel(VipLevel::query()->where('level', '>', $displayVipLevelNo)->orderBy('level')->first()),
            ...$activeTeamValue,
            'svip_package' => ($package = SvipPackage::query()->active()->orderBy('sort_order')->orderBy('id')->first()) ? ApiFormatter::svipPackage($package) : null,
            'active_svip_purchase' => ApiFormatter::svipPurchase($user->activeSvipPurchase()->with('package')->first()),
            'referral_url' => url('/register?ref='.$user->referral_code),
            'bank_account' => ApiFormatter::bankAccount($user->bankAccount()->first()),
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(array_filter($request->validated(), fn ($value) => $value !== null))->save();

        return ApiResponse::success([
            'user' => ApiFormatter::user($user->fresh()),
        ], 'Profile updated successfully.');
    }

    public function updateAvatar(ProfileAvatarRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('do_spaces')->delete($user->profile_photo_path);
        }

        $path = $request->file('avatar')->store('profile-photos', 'do_spaces');
        $user->forceFill(['profile_photo_path' => $path])->save();

        return ApiResponse::success([
            'user' => ApiFormatter::user($user->fresh()),
        ], __('profile.avatar_updated'));
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('do_spaces')->delete($user->profile_photo_path);
        }

        $user->forceFill(['profile_photo_path' => null])->save();

        return ApiResponse::success([
            'user' => ApiFormatter::user($user->fresh()),
        ], __('profile.avatar_deleted'));
    }
}
