<?php

namespace App\Http\Controllers;

use App\Models\PointTransaction;
use App\Models\Setting;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PointClaimController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $transactions = PointTransaction::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return view('points.index', [
            'user' => $user,
            'transactions' => $transactions,
            'pointsEnabled' => Setting::bool(PointService::SETTING_POINTS_ENABLED, false),
            'redeemEnabled' => Setting::bool(PointService::SETTING_REDEEM_ENABLED, false),
            'rate' => Setting::decimal(PointService::SETTING_POINTS_TO_CASH_RATE, 100),
            'minimumPoints' => Setting::decimal(PointService::SETTING_MINIMUM_REDEEM_POINTS, 0),
            'hasApprovedDeposit' => $user->hasApprovedDeposit(),
        ]);
    }

    public function redeem(Request $request, PointService $pointService): RedirectResponse
    {
        $data = $request->validate([
            'points' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $pointService->redeemToWithdrawableWallet(
                user: $request->user(),
                points: (float) $data['points'],
            );

            return back()->with('success', __('points.redeem_success'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('points.redeem_failed'));
        }
    }
}
