<?php

namespace App\Http\Controllers;

use App\Models\SalaryClaim;
use App\Models\User;
use App\Models\VipLevel;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class SalaryController extends Controller
{
    public function index(Request $request, SalaryService $salaryService): View
    {
        $user = $request->user();

        $activeTeamSize = $user->activeTeamSize();
        $activeTeamSize = (int) $this->displayOverride(
            $user,
            'display_active_team_count',
            $activeTeamSize
        );

        $accumulatedRecharge = (float) $this->displayOverride(
            $user,
            'display_accumulated_recharge',
            (float) $user->accumulated_recharge
        );

        $currentVipLevel = $salaryService->currentVipLevel($user);

        $vipLevels = VipLevel::query()
            ->where('status', 'active')
            ->orderBy('level')
            ->get();

        $types = [
            SalaryClaim::TYPE_WEEKLY,
            SalaryClaim::TYPE_MONTHLY,
            SalaryClaim::TYPE_ANNUAL,
        ];

        $claimStates = [];

        foreach ($types as $type) {
            $claimStates[$type] = [
                'period_key' => $salaryService->periodKey($type),
                'claimed' => $salaryService->hasClaimed($user, $type),
                'enabled' => match ($type) {
                    SalaryClaim::TYPE_WEEKLY => (bool) $user->weekly_salary_status,
                    SalaryClaim::TYPE_MONTHLY => (bool) $user->monthly_salary_status,
                    SalaryClaim::TYPE_ANNUAL => (bool) $user->annual_salary_status,
                    default => false,
                },
                'within_window' => $salaryService->isWithinClaimWindow($type),
                'claim_window' => $salaryService->claimWindowLabel($type),
            ];
        }

        $claims = SalaryClaim::query()
            ->with('vipLevel')
            ->where('user_id', $user->id)
            ->latest('claimed_at')
            ->paginate(10);

        return view('salary.index', [
            'user' => $user,
            'activeTeamSize' => $activeTeamSize,
            'accumulatedRecharge' => $accumulatedRecharge,
            'currentVipLevel' => $currentVipLevel,
            'vipLevels' => $vipLevels,
            'claimStates' => $claimStates,
            'claims' => $claims,
        ]);
    }

    public function claim(
        Request $request,
        string $type,
        SalaryService $salaryService,
    ): RedirectResponse {
        try {
            $salaryService->claim(
                user: $request->user(),
                type: $type,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            return back()->with('success', __('salary.claim_success'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('salary.claim_failed'));
        }
    }

    private function displayOverride(User $user, string $field, mixed $realValue): mixed
    {
        $value = $user->getAttribute($field);

        return $value !== null && $value !== ''
            ? $value
            : $realValue;
    }
}