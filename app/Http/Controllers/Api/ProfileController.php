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
        $currentVipLevel = $salaryService->currentVipLevel($user);
        $activeTeamSize = $user->activeTeamSize();

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $stats = [
            'recharge_wallet_balance' => ApiFormatter::money($rechargeWallet?->balance),
            'withdrawable_wallet_balance' => ApiFormatter::money($withdrawableWallet?->balance),
            'accumulated_recharge' => ApiFormatter::money($this->displayOverride($user, 'display_accumulated_recharge', $user->accumulated_recharge)),
            'total_earning' => ApiFormatter::money($this->displayOverride($user, 'display_total_earning', WalletTransaction::query()->where('user_id', $user->id)->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)->where('direction', WalletTransaction::DIRECTION_CREDIT)->sum('amount'))),
            'total_withdrawals' => ApiFormatter::money($this->displayOverride($user, 'display_total_withdrawals', WithdrawRequest::query()->where('user_id', $user->id)->where('status', 'approved')->sum('net_amount'))),
            'pending_withdrawals' => ApiFormatter::money(WithdrawRequest::query()->where('user_id', $user->id)->where('status', 'pending')->sum('amount')),
            'team_income' => ApiFormatter::money($this->displayOverride($user, 'display_team_income', CommissionRecord::query()->where('earner_user_id', $user->id)->where('status', 'paid')->sum('amount'))),
            'income_today' => ApiFormatter::money($this->displayOverride($user, 'display_income_today', WalletTransaction::query()->where('user_id', $user->id)->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)->where('direction', WalletTransaction::DIRECTION_CREDIT)->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount'))),
            'yesterday_earnings' => ApiFormatter::money($this->displayOverride($user, 'display_yesterday_earnings', WalletTransaction::query()->where('user_id', $user->id)->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)->where('direction', WalletTransaction::DIRECTION_CREDIT)->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->sum('amount'))),
            'daily_income_total' => ApiFormatter::money($this->displayOverride($user, 'display_daily_income_total', DailyIncomeClaim::query()->where('user_id', $user->id)->sum('amount'))),
            'salary_income_total' => ApiFormatter::money($this->displayOverride($user, 'display_salary_income_total', SalaryClaim::query()->where('user_id', $user->id)->sum('total_amount'))),
            'approved_recharge_total' => ApiFormatter::money(RechargeRequest::query()->where('user_id', $user->id)->where('status', 'approved')->sum('amount')),
            'unused_bonus_count' => BonusCode::query()->where('owner_user_id', $user->id)->where('status', BonusCode::STATUS_UNUSED)->count(),
        ];

        $displayVipLevelNo = (int) $this->displayOverride($user, 'display_vip_level', $currentVipLevel?->level ?? 1);

        return ApiResponse::success([
            'user' => ApiFormatter::user($user),
            'wallets' => [
                'recharge' => ApiFormatter::wallet($rechargeWallet, Wallet::TYPE_RECHARGE),
                'withdrawable' => ApiFormatter::wallet($withdrawableWallet, Wallet::TYPE_WITHDRAWABLE),
            ],
            'stats' => $stats,
            'current_vip_level' => ApiFormatter::vipLevel(VipLevel::query()->where('level', $displayVipLevelNo)->first() ?: $currentVipLevel),
            'next_vip_level' => ApiFormatter::vipLevel(VipLevel::query()->where('level', '>', $displayVipLevelNo)->orderBy('level')->first()),
            'active_team_size' => (int) $this->displayOverride($user, 'display_active_team_count', $activeTeamSize),
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

    private function displayOverride($user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== '' ? $value : $realValue;
    }
}
