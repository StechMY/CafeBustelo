<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class VerifyOtpChallenge
{
    public function handle(Request $request, Closure $next, string $purpose)
    {
        $request->attributes->set('otp_result', 'challenge_rejected');
        $token = $request->input('turnstile_token');

        if (! is_string($token) || $token === '' || strlen($token) > 2048) {
            throw ValidationException::withMessages(['turnstile_token' => __('otp.challenge_required')]);
        }

        if (blank(config('turnstile.secret_key')) || blank(config('turnstile.site_key')) || empty(config('turnstile.hostnames'))) {
            $request->attributes->set('otp_result', 'challenge_unconfigured');
            throw ValidationException::withMessages(['turnstile_token' => __('otp.service_unavailable')]);
        }

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(8)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                ['secret' => config('turnstile.secret_key'), 'response' => $token],
            );
            $data = $response->json();
            $valid = $response->successful()
                && is_array($data)
                && ($data['success'] ?? false) === true
                && ($data['action'] ?? null) === 'otp_'.$purpose
                && in_array($data['hostname'] ?? null, config('turnstile.hostnames'), true);

            // Cloudflare tokens are single-use; also reject replay across local requests/workers.
            if ($valid) {
                $valid = Cache::add('otp:challenge:'.hash('sha256', $token), true, 300);
            }
        } catch (Throwable) {
            // Transport exceptions may include the secret/token. Never report their payloads.
            $request->attributes->set('otp_result', 'challenge_unavailable');
            throw ValidationException::withMessages(['turnstile_token' => __('otp.service_unavailable')]);
        }

        if (! $valid) {
            throw ValidationException::withMessages(['turnstile_token' => __('otp.challenge_required')]);
        }

        $request->attributes->set('otp_result', 'validation_rejected');

        return $next($request);
    }
}
