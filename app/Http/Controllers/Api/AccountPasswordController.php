<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountPasswordController extends Controller
{
    public function sendOtp(Request $request, OtpService $otpService): JsonResponse
    {
        $user = $request->user();

        if (blank($user->phone)) {
            return ApiResponse::error(__('password.phone_not_bound'), [
                'phone' => [__('password.phone_not_bound')],
            ], 422);
        }

        $otpService->send(
            $otpService->normalizePhone((string) $user->phone),
            'change_password'
        );

        return ApiResponse::success([
            'resend_seconds' => config('sms.otp_resend_seconds'),
            'expires_in_minutes' => config('sms.otp_expire_minutes'),
        ], __('otp.sent'));
    }

    public function update(Request $request, OtpService $otpService): JsonResponse
    {
        $user = $request->user();

        if (blank($user->phone)) {
            return ApiResponse::error(__('password.phone_not_bound'), [
                'phone' => [__('password.phone_not_bound')],
            ], 422);
        }

        $validated = $request->validate([
            'otp_code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $phone = $otpService->normalizePhone((string) $user->phone);

        $otpService->verify($phone, 'change_password', $validated['otp_code']);

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return ApiResponse::success([], __('password.updated_successfully'));
    }
}
