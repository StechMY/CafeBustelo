<?php

namespace App\Http\Controllers;

use App\Models\BonusCode;
use App\Models\CommissionRecord;
use App\Models\DailyIncomeClaim;
use App\Models\RechargeRequest;
use App\Models\SalaryClaim;
use App\Models\SvipPackage;
use App\Models\User;
use App\Models\VipLevel;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WithdrawRequest;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request, SalaryService $salaryService): View
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

        $currentVipLevel = $salaryService->currentVipLevel($user);
        $activeTeamSize = method_exists($user, 'activeTeamSize')
            ? $user->activeTeamSize()
            : 0;

        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();

        $stats = [
            'recharge_wallet_balance' => $rechargeWallet?->balance ?? 0,
            'withdrawable_wallet_balance' => $withdrawableWallet?->balance ?? 0,

            'accumulated_recharge' => (float) $user->accumulated_recharge,

            'total_earning' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)
                ->where('direction', WalletTransaction::DIRECTION_CREDIT)
                ->sum('amount'),

            'total_withdrawals' => WithdrawRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->sum('net_amount'),

            'pending_withdrawals' => WithdrawRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->sum('amount'),

            'team_income' => CommissionRecord::query()
                ->where('earner_user_id', $user->id)
                ->where('status', 'paid')
                ->sum('amount'),

            'income_today' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)
                ->where('direction', WalletTransaction::DIRECTION_CREDIT)
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->sum('amount'),

            'yesterday_earnings' => WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('wallet_type', Wallet::TYPE_WITHDRAWABLE)
                ->where('direction', WalletTransaction::DIRECTION_CREDIT)
                ->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])
                ->sum('amount'),

            'daily_income_total' => DailyIncomeClaim::query()
                ->where('user_id', $user->id)
                ->sum('amount'),

            'salary_income_total' => SalaryClaim::query()
                ->where('user_id', $user->id)
                ->sum('total_amount'),

            'approved_recharge_total' => RechargeRequest::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->sum('amount'),

            'unused_bonus_count' => BonusCode::query()
                ->where('owner_user_id', $user->id)
                ->where('status', BonusCode::STATUS_UNUSED)
                ->count(),
        ];

        $displayVipLevelNo = (int) $this->displayOverride(
            $user,
            'display_vip_level',
            $currentVipLevel?->level ?? 1
        );

        $displayCurrentVipLevel = VipLevel::query()
            ->where('level', $displayVipLevelNo)
            ->first();

        $displayNextVipLevel = VipLevel::query()
            ->where('level', '>', $displayVipLevelNo)
            ->orderBy('level')
            ->first();

        $activeTeamSize = (int) $this->displayOverride(
            $user,
            'display_active_team_count',
            $activeTeamSize
        );

        $stats['accumulated_recharge'] = (float) $this->displayOverride(
            $user,
            'display_accumulated_recharge',
            $stats['accumulated_recharge']
        );

        $stats['total_earning'] = (float) $this->displayOverride(
            $user,
            'display_total_earning',
            $stats['total_earning']
        );

        $stats['total_withdrawals'] = (float) $this->displayOverride(
            $user,
            'display_total_withdrawals',
            $stats['total_withdrawals']
        );

        $stats['income_today'] = (float) $this->displayOverride(
            $user,
            'display_income_today',
            $stats['income_today']
        );

        $stats['yesterday_earnings'] = (float) $this->displayOverride(
            $user,
            'display_yesterday_earnings',
            $stats['yesterday_earnings']
        );

        $stats['team_income'] = (float) $this->displayOverride(
            $user,
            'display_team_income',
            $stats['team_income']
        );

        $stats['daily_income_total'] = (float) $this->displayOverride(
            $user,
            'display_daily_income_total',
            $stats['daily_income_total']
        );

        $stats['salary_income_total'] = (float) $this->displayOverride(
            $user,
            'display_salary_income_total',
            $stats['salary_income_total']
        );

        $svipPackage = SvipPackage::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        $activeSvipPurchase = $user->activeSvipPurchase()->first();

        return view('profile.index', [
            'user' => $user,
            'stats' => $stats,
            'currentVipLevel' => $displayCurrentVipLevel ?: $currentVipLevel,
            'displayVipLevelNo' => $displayVipLevelNo,
            'nextVipLevel' => $displayNextVipLevel,
            'activeTeamSize' => $activeTeamSize,
            'svipPackage' => $svipPackage,
            'activeSvipPurchase' => $activeSvipPurchase,
        ]);
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if ($user->profile_photo_path) {
            Storage::disk('do_spaces')->delete($user->profile_photo_path);
        }

        $path = $data['avatar']->store('profile-photos', 'do_spaces');

        $user->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        return back()->with('success', __('profile.avatar_updated'));
    }

    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->profile_photo_path) {
            Storage::disk('do_spaces')->delete($user->profile_photo_path);
        }

        $user->forceFill([
            'profile_photo_path' => null,
        ])->save();

        return back()->with('success', __('profile.avatar_deleted'));
    }


    private function displayOverride(User $user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== ''
            ? $value
            : $realValue;
    }
}