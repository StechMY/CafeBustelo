<?php

namespace App\Http\Controllers;

use App\Models\BankAccountReviewRequest;
use App\Services\BankAccountReviewService;
use App\Services\OtpService;
use App\Support\Qc168VietnamBank;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

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
            'reviewRequest' => $user->bankAccountReviewRequests()->latest('id')->first(),
        ]);
    }

    public function store(
        Request $request,
        OtpService $otpService,
        BankAccountReviewService $reviewService,
    ): RedirectResponse {
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

        $latestReview = $user->bankAccountReviewRequests()->latest('id')->first();

        if ($latestReview?->isPending()) {
            return redirect()
                ->route('bank-account.create')
                ->with('warning', __('bank_account.under_review'));
        }

        $validated = $request->validate([
            'bank_account_name' => [
                'required', 'string', 'max:150',
            ],
            'bank_name' => [
                'required',
                'string',
                'max:100',
                Rule::in(array_keys(Qc168VietnamBank::options())),
            ],
            'bank_account_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('user_bank_accounts', 'bank_account_no'),
                Rule::unique('bank_account_review_requests', 'bank_account_no')
                    ->where(fn ($query) => $query->where('status', BankAccountReviewRequest::STATUS_PENDING))
                    ->ignore($latestReview?->id),
            ],
            'phone' => [
                'required',
                'string',
                'max:40',
                'regex:/^[0-9]+$/',
            ],
            'otp_code' => [
                'required',
                'string',
                'digits:6',
            ],
            'id_photos' => [
                Rule::requiredIf(! $latestReview?->isRejected()),
                'array',
                'min:1',
                'max:5',
            ],
            'id_photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'bank_account_no.unique' => __('bank_account.account_no_already_bound'),
        ]);

        $otpService->verify($validated['phone'], 'bank_account', $validated['otp_code']);

        try {
            $reviewService->submit($user, [
                ...$validated,
                'phone_verified_at' => now(),
                'meta' => [
                    'source' => 'web_bank_account_review',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ], $request->file('id_photos', []));
        } catch (RuntimeException $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'bank_account_no' => __('bank_account.account_no_already_bound'),
                ]);
        }

        return redirect()
            ->route('bank-account.create')
            ->with('success', __('bank_account.submitted_for_review'));
    }
}
