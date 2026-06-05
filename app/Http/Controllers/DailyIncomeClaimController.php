<?php

namespace App\Http\Controllers;

use App\Models\UserProduct;
use App\Services\DailyIncomeClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class DailyIncomeClaimController extends Controller
{
    public function store(
        Request $request,
        UserProduct $userProduct,
        DailyIncomeClaimService $dailyIncomeClaimService,
    ): RedirectResponse {
        abort_unless((int) $userProduct->user_id === (int) $request->user()->id, 403);

        try {
            $dailyIncomeClaimService->claim(
                user: $request->user(),
                userProduct: $userProduct,
                source: 'manual_claim',
            );

            return back()->with('success', __('daily_income.claim_success'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', __('daily_income.claim_failed'));
        }
    }
}