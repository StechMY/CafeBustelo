<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RegisterOtpController extends Controller
{
    public function send(Request $request, OtpService $otpService): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'max:40']]);

        $request->merge([
            'phone' => $otpService->normalizePhone((string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'phone' => [
                'required',
                'string',
                'max:40',
                Rule::unique('users', 'phone'),
            ],
        ]);

        $otpService->send($validated['phone'], 'register');

        return ApiResponse::success([
            'resend_seconds' => config('sms.otp_resend_seconds'),
            'expires_in_minutes' => config('sms.otp_expire_minutes'),
        ], __('otp.sent'));
    }
}
