<?php

namespace App\Http\Controllers;

use App\Models\UserBankAccount;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\OtpService;

class BankAccountController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->bankAccount()->where('status', 'active')->exists()) {
            return redirect()
                ->route('withdraw.create')
                ->with('info', __('bank_account.already_bound'));
        }

        return view('bank-account.create', [
            'user' => $user,
        ]);
    }

    public function store(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if ($user->bankAccount()->where('status', 'active')->exists()) {
            return redirect()
                ->route('withdraw.create')
                ->with('info', __('bank_account.already_bound'));
        }

        $request->merge([
            'bank_account_name' => trim((string) $request->input('bank_account_name')),
            'bank_name' => trim((string) $request->input('bank_name')),
            'bank_account_no' => preg_replace('/\s+/', '', trim((string) $request->input('bank_account_no'))),
            'phone' => $otpService->normalizePhone((string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'bank_account_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('user_bank_accounts', 'bank_account_name'),
            ],
            'bank_name' => [
                'required',
                'string',
                'max:100',
            ],
            'bank_account_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('user_bank_accounts', 'bank_account_no'),
            ],
            'phone' => [
                'required',
                'string',
                'max:40',
            ],
            'otp_code' => [
                'required',
                'string',
                'digits:6',
            ],
        ], [
            'bank_account_name.unique' => __('bank_account.name_already_bound'),
            'bank_account_no.unique' => __('bank_account.account_no_already_bound'),
        ]);

        $otpService->verify($validated['phone'], 'bank_account', $validated['otp_code']);

        try {
            DB::transaction(function () use ($user, $validated): void {
                UserBankAccount::query()->create([
                    'user_id' => $user->id,
                    'bank_account_name' => $validated['bank_account_name'],
                    'bank_name' => $validated['bank_name'],
                    'bank_account_no' => $validated['bank_account_no'],
                    'phone' => $validated['phone'],
                    'phone_verified_at' => now(),
                    'status' => 'active',
                    'is_locked' => true,
                    'bound_at' => now(),
                    'meta' => [
                        'source' => 'first_withdraw_bind',
                        'phone_verified_at' => now()->toDateTimeString(),
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                    ],
                ]);
            });
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'bank_account_no' => __('bank_account.bind_failed_duplicate'),
                ]);
        }

        return redirect()
            ->route('withdraw.create')
            ->with('success', __('bank_account.bind_success'));
    }
}