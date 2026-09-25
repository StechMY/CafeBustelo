<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountOtpController extends Controller
{
    public function send(Request $request, OtpService $otpService): JsonResponse
    {
        $user = $request->user();

        if ($user->bankAccount()->where('status', 'active')->exists()) {
            return ApiResponse::error(__('bank_account.already_bound'), [], 422);
        }

        $request->validate(['phone' => ['required', 'string', 'max:40']]);

        $request->merge([
            'phone' => $otpService->normalizePhone((string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $otpService->send($validated['phone'], 'bank_account');

        return ApiResponse::success([
            'resend_seconds' => config('sms.otp_resend_seconds'),
            'expires_in_minutes' => config('sms.otp_expire_minutes'),
        ], __('otp.sent'));
    }
}
