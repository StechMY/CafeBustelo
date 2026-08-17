<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccountReviewRequest;
use App\Services\BankAccountReviewService;
use App\Services\OtpService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class BankAccountController extends Controller
{
    public function store(
        Request $request,
        OtpService $otpService,
        BankAccountReviewService $reviewService,
    ): JsonResponse {
        $user = $request->user();

        if ($user->bankAccount()->where('status', 'active')->exists()) {
            return ApiResponse::error(__('bank_account.already_bound'), [], 422);
        }

        $request->merge([
            'bank_account_name' => trim((string) $request->input('bank_account_name')),
            'bank_name' => trim((string) $request->input('bank_name')),
            'bank_account_no' => preg_replace('/\s+/', '', trim((string) $request->input('bank_account_no'))),
            'phone' => $otpService->normalizePhone((string) $request->input('phone')),
        ]);

        $latestReview = $user->bankAccountReviewRequests()->latest('id')->first();

        if ($latestReview?->isPending()) {
            return ApiResponse::error(__('bank_account.under_review'), [], 422);
        }

        $validated = $request->validate([
            'bank_account_name' => [
                'required', 'string', 'max:150',
            ],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_no' => [
                'required',
                'string',
                'max:100',
                Rule::unique('user_bank_accounts', 'bank_account_no'),
                Rule::unique('bank_account_review_requests', 'bank_account_no')
                    ->where(fn ($query) => $query->where('status', BankAccountReviewRequest::STATUS_PENDING))
                    ->ignore($latestReview?->id),
            ],
            'phone' => ['required', 'string', 'max:40'],
            'otp_code' => ['required', 'string', 'digits:6'],
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
            $reviewRequest = $reviewService->submit($user, [
                ...$validated,
                'phone_verified_at' => now(),
                'meta' => [
                    'source' => 'api_bank_account_review',
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ], $request->file('id_photos', []));
        } catch (RuntimeException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        } catch (QueryException $exception) {
            report($exception);

            return ApiResponse::error('Validation failed', [
                'bank_account_no' => [__('bank_account.account_no_already_bound')],
            ], 422);
        }

        return ApiResponse::success([
            'bank_account_review' => ApiFormatter::bankAccountReview($reviewRequest),
        ], __('bank_account.submitted_for_review'), 202);
    }
}
