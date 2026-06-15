<?php

namespace App\Http\Controllers\Auth;

use App\Services\WelcomeBonusService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Services\PointService;

class CustomerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(Request $request): View
    {
        return view('auth.register', [
            'referralCode' => $request->query('ref'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors([
                    'username' => 'Invalid username or password.',
                ])
                ->onlyInput('username');
        }

        $request->session()->regenerate();
        $user = $request->user();

        $user->forceFill([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ])->save();

        $user->loginLogs()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
            'meta' => [
                'type' => 'login',
            ],
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function register(Request $request, WelcomeBonusService $welcomeBonusService, PointService $pointService): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'alpha_dash',
                'min:4',
                'max:32',
                Rule::unique('users', 'username'),
            ],

            'phone' => ['required', 'string', 'max:32'],

            'referral_code' => [
                'required',
                'string',
                'exists:users,referral_code',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $referrer = null;

        if (!empty($data['referral_code'])) {
            $referrer = User::query()
                ->where('referral_code', $data['referral_code'])
                ->first();
        }

        $user = DB::transaction(function () use ($data, $referrer, $request, $welcomeBonusService, $pointService): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'referrer_id' => $referrer?->id,
                'referral_code' => $this->generateReferralCode(),
                'status' => 'inactive',
                'is_virtual' => false,
                'accumulated_recharge' => 0,
                'registered_ip' => $request->ip(),
                'last_login_ip' => $request->ip(),
                'last_login_at' => now(),
                'order_status' => true,
                'withdraw_status' => true,
            ]);

            Wallet::query()->firstOrCreate([
                'user_id' => $user->id,
                'type' => Wallet::TYPE_RECHARGE,
            ], [
                'balance' => 0,
                'locked_balance' => 0,
            ]);

            Wallet::query()->firstOrCreate([
                'user_id' => $user->id,
                'type' => Wallet::TYPE_WITHDRAWABLE,
            ], [
                'balance' => 0,
                'locked_balance' => 0,
            ]);

            if ($referrer) {
                $pointService->giveInviteDayRegisterPoints($referrer, $user);
            }
            $welcomeBonusService->give($user);

            return $user;
        });

        Auth::login($user);

        $user->loginLogs()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
            'meta' => [
                'type' => 'register_login',
            ],
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Account created successfully.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Logged out successfully.');
    }

    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }
}