<?php

namespace App\Http\Middleware;

use App\Services\OtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class AuditOtpSend
{
    public function handle(Request $request, Closure $next, string $purpose)
    {
        $id = (string) Str::uuid();
        $status = 500;
        $request->attributes->set('otp_result', 'rejected');

        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $id);

            return $response;
        } catch (Throwable $exception) {
            $status = $exception instanceof ValidationException ? 422
                : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            throw $exception;
        } finally {
            $phone = $request->attributes->get('otp_phone', $purpose === 'change_password'
                ? $request->user()?->phone : $request->input('phone'));
            $phone = is_string($phone) ? app(OtpService::class)->normalizePhone($phone) : '';

            Log::channel('otp')->info('otp.send', [
                'request_id' => $id,
                'route' => $request->route()?->getName(),
                'purpose' => $purpose,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ip' => $request->ip(),
                'peer_ip' => $request->server('REMOTE_ADDR'),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'phone_hash' => $phone === '' ? null : hash_hmac('sha256', $phone, (string) config('app.key')),
                'phone_suffix' => preg_match('/^84[35789][0-9]{8}$/D', $phone) ? substr($phone, -4) : null,
                'status' => $status,
                'result' => $status === 429 ? 'rate_limited' : $request->attributes->get('otp_result'),
                'sms_uid' => $request->attributes->get('otp_sms_uid'),
            ]);
        }
    }
}
