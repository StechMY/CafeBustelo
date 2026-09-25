<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankAccountOtpController extends Controller
{
    public function send(Request $request, OtpService $otpService): JsonResponse
    {
        $user = $request->user();

        if ($user->bankAccount()->where('status', 'active')->exists()) {
            return response()->json([
                'success' => false,
                'message' => __('bank_account.already_bound'),
            ], 422);
        }

        $request->validate(['phone' => ['required', 'string', 'max:40']]);

        $request->merge([
            'phone' => $otpService->normalizePhone((string) $request->input('phone')),
        ]);

        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:40'],
        ]);

        $otpService->send($validated['phone'], 'bank_account');

        return response()->json([
            'success' => true,
            'message' => __('otp.sent'),
            'resend_seconds' => config('sms.otp_resend_seconds'),
        ]);
    }
}