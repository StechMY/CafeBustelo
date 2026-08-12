<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.password', [
            'user' => $request->user(),
        ]);
    }

    public function sendOtp(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if (blank($user->phone)) {
            return back()->withErrors([
                'phone' => __('password.phone_not_bound'),
            ]);
        }

        $otpService->send(
            $otpService->normalizePhone((string) $user->phone),
            'change_password'
        );

        return back()->with('success', __('otp.sent'));
    }

    public function update(Request $request, OtpService $otpService): RedirectResponse
    {
        $user = $request->user();

        if (blank($user->phone)) {
            return back()->withErrors([
                'phone' => __('password.phone_not_bound'),
            ]);
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

        return redirect()
            ->route('settings.password.edit')
            ->with('success', __('password.updated_successfully'));
    }
}
