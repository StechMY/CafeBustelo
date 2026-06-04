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

    public function create(Request $request): View
    {
        $user = $request->user();

        $wallet = Wallet::query()
            ->where('user_id', $user->id)
            ->where('type', Wallet::TYPE_WITHDRAWABLE)
            ->first();

        $vipLevel = $user->currentVipLevel();

        return view('withdraw.create', [
            'user' => $user,
            'withdrawableBalance' => $wallet?->balance ?? 0,
            'lockedBalance' => $wallet?->locked_balance ?? 0,
            'vipLevel' => $vipLevel,
            'minWithdrawAmount' => $vipLevel ? (float) $vipLevel->min_withdraw_amount : 0,
        ]);
    }

    public function store(Request $request, WithdrawService $withdrawService): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_name' => ['required', 'string', 'max:150'],
            'bank_account_no' => ['required', 'string', 'max:100'],
        ]);

        try {
            $withdrawRequest = $withdrawService->submit(
                user: $request->user(),
                amount: $data['amount'],
                bankName: $data['bank_name'],
                bankAccountName: $data['bank_account_name'],
                bankAccountNo: $data['bank_account_no'],
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
                ->with('error', __('withdraw.submit_failed'));
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