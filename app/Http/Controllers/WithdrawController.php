<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\WithdrawRequest;
use App\Services\WithdrawService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class WithdrawController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $requests = WithdrawRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(10);

        return view('withdraw.index', [
            'user' => $user,
            'withdrawableBalance' => $wallet?->balance ?? 0,
            'lockedBalance' => $wallet?->locked_balance ?? 0,
            'requests' => $requests,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $bankAccount = $user->bankAccount()->where('status', 'active')->first();

        if (! $bankAccount) {
            $isUnderReview = $user->bankAccountReviewRequests()
                ->where('status', 'pending')
                ->exists();

            return redirect()
                ->route('bank-account.create')
                ->with('warning', $isUnderReview
                    ? __('bank_account.under_review')
                    : __('bank_account.bind_required_first'));
        }

        $vipLevel = $user->currentVipLevel();

        $minWithdrawAmount = $vipLevel ? (float) $vipLevel->min_withdraw_amount : 0;
        $withdrawFeePercent = $vipLevel && isset($vipLevel->withdraw_fee_percent)
            ? (float) $vipLevel->withdraw_fee_percent
            : 0;

        return view('withdraw.create', [
            'user' => $user,
            'withdrawableBalance' => $wallet?->balance ?? 0,
            'lockedBalance' => $wallet?->locked_balance ?? 0,
            'vipLevel' => $vipLevel,
            'minWithdrawAmount' => $minWithdrawAmount,
            'withdrawFeePercent' => $withdrawFeePercent,
            'bankAccount' => $bankAccount,
        ]);
    }

    public function store(Request $request, WithdrawService $withdrawService): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
        ]);

        try {
            $withdrawRequest = $withdrawService->submit(
                user: $request->user(),
                amount: $data['amount'],
            );

            return redirect()
                ->route('withdraw.show', $withdrawRequest)
                ->with('success', __('withdraw.submitted_successfully'));
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }
    }

    public function show(Request $request, WithdrawRequest $withdrawRequest): View
    {
        abort_unless((int) $withdrawRequest->user_id === (int) $request->user()->id, 403);

        return view('withdraw.show', [
            'withdrawRequest' => $withdrawRequest,
        ]);
    }
}
