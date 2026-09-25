<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OtpService;
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

        return response()->json([
            'success' => true,
            'message' => __('otp.sent'),
            'resend_seconds' => config('sms.otp_resend_seconds'),
        ]);
    }
}