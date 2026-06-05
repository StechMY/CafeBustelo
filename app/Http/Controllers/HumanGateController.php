<?php

namespace App\Http\Controllers;

use App\Models\CompanyContactVerification;
use App\Services\CompanyBot\HumanRiskScorer;
use App\Services\CompanyBot\RequestBotDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class HumanGateController extends Controller
{
    public function show(Request $request, RequestBotDetector $detector)
    {
        if ($detector->isSocialBot($request)) {
            return response()
                ->view('social-preview', [
                    'company' => config('companybot.company'),
                ])
                ->header('X-Robots-Tag', 'noindex, noarchive, nosnippet');
        }

        if ($detector->should404Immediately($request)) {
            abort(404);
        }

        return response()
            ->view('gate', [
                'verifyUrl' => route('human.gate.verify'),
            ])
            ->header('X-Robots-Tag', 'noindex, noarchive, nosnippet')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function verify(
        Request $request,
        RequestBotDetector $detector,
        HumanRiskScorer $scorer
    ) {
        if ($detector->isSocialBot($request)) {
            abort(404);
        }

        if ($detector->should404Ajax($request)) {
            abort(404);
        }

        $rateKey = 'human-gate-verify:' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, config('companybot.verify_rate_limit_per_ip_per_minute'))) {
            abort(404);
        }

        RateLimiter::hit($rateKey, 60);

        $validator = Validator::make($request->all(), [
            'visitor_id' => ['required', 'string', 'max:100'],
            'fingerprint_hash' => ['nullable', 'string', 'max:128'],
            'signals' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            abort(404);
        }

        $visitorId = $request->input('visitor_id');
        $fingerprintHash = $request->input('fingerprint_hash');
        $signals = $request->input('signals', []);

        $result = $scorer->score($request, $signals);
        $sessionToken = Str::random(64);
        $rememberToken = Str::random(80);
        $rememberTokenHash = hash('sha256', $rememberToken);

        CompanyContactVerification::create([
            'visitor_id' => $visitorId,
            'fingerprint_hash' => $fingerprintHash,
            'remember_token_hash' => $rememberTokenHash,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1024),
            'risk_score' => $result['score'],
            'decision' => $result['decision'],
            'reasons' => $result['reasons'],
            'signals' => app()->environment('local')
                ? $signals
                : $this->minimizeSignals($signals),
            'verified_at' => $result['decision'] === 'allow' ? now() : null,
            'expires_at' => $result['decision'] === 'allow'
                ? now()->addMinutes(config('companybot.verified_ttl_minutes'))
                : null,
            'remember_expires_at' => $result['decision'] === 'allow'
                ? now()->addDays(config('companybot.remember_device_days'))
                : null,
        ]);

        if ($result['decision'] !== 'allow') {
            abort(404);
        }

        $sessionToken = Str::random(64);

        $request->session()->put('human_verified', [
            'visitor_id' => $visitorId,
            'fingerprint_hash' => $fingerprintHash,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1024),
            'session_token' => $sessionToken,
            'verified_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(config('companybot.verified_ttl_minutes'))->timestamp,
        ]);

        $intendedUrl = $request->session()->pull('human_intended_url', '/');



        $sessionCookie = Cookie::make(
            'human_verified',
            $sessionToken,
            config('companybot.verified_ttl_minutes'),
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        );

        $rememberCookie = Cookie::make(
            'human_remember',
            $rememberToken,
            60 * 24 * config('companybot.remember_device_days'),
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        );

        return response()
            ->json([
                'ok' => true,
                'redirect' => $intendedUrl,
            ])
            ->withCookie($sessionCookie)
            ->withCookie($rememberCookie)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('X-Robots-Tag', 'noindex, noarchive, nosnippet');
    }

    protected function minimizeSignals(array $signals): array
    {
        return [
            'screen' => $signals['screen'] ?? null,
            'navigator' => [
                'language' => $signals['navigator']['language'] ?? null,
                'languages' => $signals['navigator']['languages'] ?? null,
                'platform' => $signals['navigator']['platform'] ?? null,
                'vendor' => $signals['navigator']['vendor'] ?? null,
                'hardwareConcurrency' => $signals['navigator']['hardwareConcurrency'] ?? null,
                'deviceMemory' => $signals['navigator']['deviceMemory'] ?? null,
            ],
            'timezone' => $signals['timezone'] ?? null,
            'webgl' => $signals['webgl'] ?? null,
            'automation' => $signals['automation'] ?? null,
            'behavior' => $signals['behavior'] ?? null,
            'visibility' => $signals['visibility'] ?? null,
            'storage' => $signals['storage'] ?? null,
        ];
    }
}