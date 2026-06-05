<?php

namespace App\Http\Middleware;

use App\Services\CompanyBot\RequestBotDetector;
use Closure;
use Illuminate\Http\Request;
use App\Models\CompanyContactVerification;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class HumanOnly
{
    public function handle(Request $request, Closure $next)
    {
        $detector = app(RequestBotDetector::class);

        /*
        |--------------------------------------------------------------------------
        | Social bot: allow OG preview only
        |--------------------------------------------------------------------------
        |
        | 注意：这里不要让 social bot 进入真正页面。
        | 给它一个通用 OG preview。
        |
        */

        if ($detector->isSocialBot($request)) {
            return response()
                ->view('social-preview', [
                    'company' => config('companybot.company'),
                ])
                ->header('X-Robots-Tag', 'noindex, noarchive, nosnippet')
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('X-Frame-Options', 'DENY')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        /*
        |--------------------------------------------------------------------------
        | Obvious bot: 404
        |--------------------------------------------------------------------------
        */

        if ($detector->should404Immediately($request)) {
            abort(404);
        }

        /*
        |--------------------------------------------------------------------------
        | Already verified human
        |--------------------------------------------------------------------------
        */

        $verified = $request->session()->get('human_verified');

        if (
            is_array($verified) &&
            ($verified['ip'] ?? null) === $request->ip() &&
            ($verified['user_agent'] ?? null) === substr((string) $request->userAgent(), 0, 1024) &&
            ($verified['session_token'] ?? null) === $request->cookie('human_verified') &&
            (int) ($verified['expires_at'] ?? 0) >= now()->timestamp
        ) {
            return $this->secureResponse($next($request));
        }

        $request->session()->forget('human_verified');

        /*
        |--------------------------------------------------------------------------
        | Not verified: go to common gate
        |--------------------------------------------------------------------------
        */

        $restored = $this->restoreRememberedHuman($request);

        if ($restored) {
            return $this->secureResponse($next($request));
        }

        $request->session()->put('human_intended_url', $request->fullUrl());

        return redirect()->route('human.gate');
    }

    protected function secureResponse($response)
    {
        return $response
            ->header('X-Robots-Tag', 'noindex, noarchive, nosnippet')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    protected function restoreRememberedHuman(Request $request): bool
    {
        $rememberToken = $request->cookie('human_remember');

        if (!$rememberToken) {
            return false;
        }

        $rememberTokenHash = hash('sha256', $rememberToken);

        $record = CompanyContactVerification::where('remember_token_hash', $rememberTokenHash)
            ->where('decision', 'allow')
            ->where('remember_expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (!$record) {
            return false;
        }

        if ($record->user_agent !== substr((string) $request->userAgent(), 0, 1024)) {
            return false;
        }

        /*
         * 公司联系方式页建议不要强绑定 IP。
         * 手机网络 IP 变动会导致一直重新验证。
         */

        $sessionToken = Str::random(64);

        $request->session()->put('human_verified', [
            'visitor_id' => $record->visitor_id,
            'fingerprint_hash' => $record->fingerprint_hash,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1024),
            'session_token' => $sessionToken,
            'verified_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(config('companybot.verified_ttl_minutes'))->timestamp,
        ]);

        Cookie::queue(Cookie::make(
            'human_verified',
            $sessionToken,
            config('companybot.verified_ttl_minutes'),
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'Lax'
        ));

        return true;
    }
}