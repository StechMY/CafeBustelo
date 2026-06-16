<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PointService;
use App\Services\WelcomeBonusService;
use App\Support\Api\ApiFormatter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request,
        WelcomeBonusService $welcomeBonusService,
        PointService $pointService,
    ): JsonResponse {
        $data = $request->validated();

        $referrer = User::query()
            ->where('referral_code', $data['referral_code'])
            ->firstOrFail();

        $user = DB::transaction(function () use ($data, $request, $referrer, $welcomeBonusService, $pointService): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'referrer_id' => $referrer->id,
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

            foreach ([Wallet::TYPE_RECHARGE, Wallet::TYPE_WITHDRAWABLE] as $type) {
                Wallet::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'type' => $type,
                ], [
                    'balance' => 0,
                    'locked_balance' => 0,
                ]);
            }

            $pointService->giveInviteDayRegisterPoints($referrer, $user);
            $welcomeBonusService->give($user);

            return $user;
        });

        $this->recordLogin($request, $user, 'register_login');

        $token = $user->createToken($data['device_name'] ?? 'flutter')->plainTextToken;

        return ApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => ApiFormatter::user($user->fresh()),
        ], 'Account created successfully.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::query()
            ->where('username', $data['username'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Invalid username or password.', [
                'username' => ['Invalid username or password.'],
            ], 401);
        }

        $this->recordLogin($request, $user, 'login');

        $token = $user->createToken($data['device_name'] ?? 'flutter')->plainTextToken;

        return ApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => ApiFormatter::user($user->fresh()),
        ], 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success([], 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => ApiFormatter::user($request->user()),
        ]);
    }

    private function recordLogin(Request $request, User $user, string $type): void
    {
        $user->forceFill([
            'last_login_ip' => $request->ip(),
            'last_login_at' => now(),
        ])->save();

        $user->loginLogs()->create([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'login_at' => now(),
            'meta' => ['type' => $type],
        ]);
    }

    private function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::query()->where('referral_code', $code)->exists());

        return $code;
    }
}
