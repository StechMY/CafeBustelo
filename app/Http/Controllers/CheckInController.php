<?php

namespace App\Http\Controllers;

use App\Models\CheckInRecord;
use App\Models\VipLevel;
use App\Services\CheckInService;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class CheckInController extends Controller
{
    public function index(
        Request $request,
        CheckInService $checkInService,
        SalaryService $salaryService,
    ): View {
        $user = $request->user();

        $currentVipLevel = $salaryService->currentVipLevel($user);
        $hasClaimedToday = $checkInService->hasClaimedToday($user);
        $todayRecord = $checkInService->todayRecord($user);

        $vipLevels = VipLevel::query()
            ->where('status', 'active')
            ->orderBy('level')
            ->get();

        $records = CheckInRecord::query()
            ->with('vipLevel')
            ->where('user_id', $user->id)
            ->latest('claimed_at')
            ->paginate(15);

        $totalClaimed = CheckInRecord::query()
            ->where('user_id', $user->id)
            ->sum('amount');

        $thisMonthClaimed = CheckInRecord::query()
            ->where('user_id', $user->id)
            ->whereBetween('claimed_at', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ])
            ->sum('amount');

        return view('check-in.index', [
            'user' => $user,
            'currentVipLevel' => $currentVipLevel,
            'hasClaimedToday' => $hasClaimedToday,
            'todayRecord' => $todayRecord,
            'vipLevels' => $vipLevels,
            'records' => $records,
            'totalClaimed' => $totalClaimed,
            'thisMonthClaimed' => $thisMonthClaimed,
        ]);
    }

    public function claim(
        Request $request,
        CheckInService $checkInService,
    ): RedirectResponse {
        try {
            $checkInService->claim(
                user: $request->user(),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            );

            return back()->with('success', __('checkin.claim_success'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('checkin.claim_failed'));
        }
    }
}