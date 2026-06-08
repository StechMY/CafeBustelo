<?php

namespace App\Http\Controllers;

use App\Models\UserBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->bankAccount()->where('status','active')->exists()) {
            return redirect()
                ->route('withdraw.create')
                ->with('info', __('bank_account.already_bound'));
        }

        return view('bank-account.create', [
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->bankAccount()->where('status','active')->exists()) {
            return redirect()
                ->route('withdraw.create')
                ->with('info', __('bank_account.already_bound'));
        }

        $validated = $request->validate([
            'bank_account_name' => ['required', 'string', 'max:150'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('user_bank_accounts', 'bank_account_no'),
            ],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            UserBankAccount::query()->create([
                'user_id' => $user->id,
                'bank_account_name' => trim($validated['bank_account_name']),
                'bank_name' => trim($validated['bank_name']),
                'bank_account_no' => preg_replace('/\s+/', '', trim($validated['bank_account_no'])),
                'status' => 'active',
                'is_locked' => true,
                'bound_at' => now(),
                'meta' => [
                    'source' => 'first_withdraw_bind',
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ],
            ]);
        });

        return redirect()
            ->route('withdraw.create')
            ->with('success', __('bank_account.bind_success'));
    }
}