<?php

namespace App\Services\Sms;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsBalanceService
{
    public const LAST_SUCCESS_KEY = 'sms_provider_balance:last_success';

    public const LAST_CHECKED_KEY = 'sms_provider_balance:last_checked';

    public const STALE_KEY = 'sms_provider_balance:stale';

    public const HEALTH_KEY = 'sms_provider_balance:health';

    public const ERROR_CODE_KEY = 'sms_provider_balance:error_code';

    public const REFRESH_LOCK_KEY = 'sms_provider_balance:refresh_lock';

    public const MANUAL_COOLDOWN_KEY = 'sms_provider_balance:manual_cooldown';

    private const CRITICAL_SEND_ERRORS = ['F0006', 'F0007', 'F0008', 'F0014', 'F0017'];

    public function getBalance(bool $force = false): array
    {
        if ($force) {
            return $this->refresh();
        }

        try {
            if (! $this->shouldRefresh()) {
                return $this->currentState();
            }

            return $this->refreshWithLock();
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');

            return $this->unavailableState();
        }
    }

    public function refresh(): array
    {
        try {
            if (! Cache::add(
                self::MANUAL_COOLDOWN_KEY,
                true,
                (int) config('sms.balance_manual_refresh_cooldown', 10),
            )) {
                return $this->currentState();
            }

            return $this->refreshWithLock(force: true);
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');

            return $this->unavailableState();
        }
    }

    public function markStale(): void
    {
        try {
            Cache::put(self::STALE_KEY, true, $this->retentionSeconds());
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');
        }
    }

    public function isStale(): bool
    {
        try {
            return (bool) Cache::get(self::STALE_KEY, true);
        } catch (Throwable) {
            return true;
        }
    }

    public function lastKnownBalance(): ?array
    {
        try {
            $balance = Cache::get(self::LAST_SUCCESS_KEY);

            return is_array($balance) ? $balance : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function recordSendResult(?string $code): void
    {
        $code = $this->sanitizeCode($code);

        if ($code === '00000') {
            $this->markStale();
            $this->clearSendError();

            return;
        }

        if ($code === 'F0015') {
            $this->storeHealth('insufficient', $code);

            return;
        }

        if (in_array($code, self::CRITICAL_SEND_ERRORS, true)) {
            $this->storeHealth('provider_error', $code);
        }
    }

    private function refreshWithLock(bool $force = false): array
    {
        $result = Cache::lock(self::REFRESH_LOCK_KEY, 20)->get(function () use ($force): array {
            if (! $force && ! $this->shouldRefresh()) {
                return $this->currentState();
            }

            return $this->fetchProviderBalance();
        });

        return is_array($result) ? $result : $this->currentState();
    }

    private function shouldRefresh(): bool
    {
        $lastChecked = Cache::get(self::LAST_CHECKED_KEY);

        if (! is_int($lastChecked)) {
            return true;
        }

        $age = max(0, now()->timestamp - $lastChecked);
        $interval = $this->isStale()
            ? (int) config('sms.balance_refresh_min_interval', 30)
            : (int) config('sms.balance_cache_seconds', 300);

        return $age >= $interval;
    }

    private function fetchProviderBalance(): array
    {
        $appKey = config('sms.appkey');
        $appCode = config('sms.appcode');
        $appSecret = config('sms.appsecret');

        if (! config('sms.enabled') || blank($appKey) || blank($appCode) || blank($appSecret)) {
            return $this->rememberFailure('CONFIGURATION');
        }

        $timestamp = (string) now()->getTimestampMs();
        $sign = md5($appKey.$appSecret.$timestamp);
        $httpStatus = null;

        try {
            $response = Http::timeout(10)->get(
                rtrim((string) config('sms.base_url'), '/').'/sms/balance/v1',
                [
                    'appkey' => $appKey,
                    'appcode' => $appCode,
                    'sign' => $sign,
                    'timestamp' => $timestamp,
                ],
            );
            $httpStatus = $response->status();

            if (! $response->successful()) {
                return $this->rememberFailure('HTTP_ERROR', $httpStatus);
            }

            $data = $response->json();

            if (! is_array($data)) {
                return $this->rememberFailure('INVALID_RESPONSE', $httpStatus);
            }

            $providerCode = $this->sanitizeCode($data['code'] ?? null);

            if ($providerCode && $providerCode !== '00000') {
                return $this->rememberFailure($providerCode, $httpStatus);
            }

            $rawBalance = $data['balance'] ?? null;
            $balance = filter_var($rawBalance, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);

            if ($balance === false) {
                return $this->rememberFailure('INVALID_RESPONSE', $httpStatus);
            }

            $fetchedAt = now()->toIso8601String();
            $lastSuccess = [
                'balance' => $balance,
                'provider_balance_time' => is_string($data['balance_time'] ?? null)
                    ? $data['balance_time']
                    : null,
                'fetched_at' => $fetchedAt,
            ];

            Cache::put(self::LAST_SUCCESS_KEY, $lastSuccess, $this->retentionSeconds());
            Cache::put(self::LAST_CHECKED_KEY, now()->timestamp, $this->retentionSeconds());
            Cache::put(self::STALE_KEY, false, $this->retentionSeconds());
            Cache::put(self::HEALTH_KEY, $this->healthForBalance($balance), $this->retentionSeconds());
            Cache::forget(self::ERROR_CODE_KEY);

            return $this->currentState();
        } catch (Throwable) {
            return $this->rememberFailure('REQUEST_FAILED', $httpStatus);
        }
    }

    private function rememberFailure(?string $code, ?int $httpStatus = null): array
    {
        $code = $this->sanitizeCode($code) ?? 'PROVIDER_ERROR';
        $health = ($code === 'F0015' || (Cache::get(self::HEALTH_KEY) === 'insufficient' && in_array($code, ['HTTP_ERROR', 'REQUEST_FAILED'], true)))
            ? 'insufficient'
            : 'provider_error';

        Cache::put(self::LAST_CHECKED_KEY, now()->timestamp, $this->retentionSeconds());
        Cache::put(self::STALE_KEY, true, $this->retentionSeconds());
        Cache::put(self::HEALTH_KEY, $health, $this->retentionSeconds());
        Cache::put(self::ERROR_CODE_KEY, $code, $this->retentionSeconds());

        Log::warning('SMS balance lookup failed.', array_filter([
            'code' => $code,
            'http_status' => $httpStatus,
        ], fn (mixed $value): bool => $value !== null));

        return $this->currentState();
    }

    private function currentState(): array
    {
        $lastSuccess = Cache::get(self::LAST_SUCCESS_KEY);

        if (! is_array($lastSuccess) || ! is_int($lastSuccess['balance'] ?? null)) {
            $lastSuccess = null;
        }

        $health = (string) Cache::get(
            self::HEALTH_KEY,
            $lastSuccess ? $this->healthForBalance($lastSuccess['balance']) : 'unknown',
        );
        $lastChecked = Cache::get(self::LAST_CHECKED_KEY);

        return [
            'balance' => $lastSuccess['balance'] ?? null,
            'provider_balance_time' => $lastSuccess['provider_balance_time'] ?? null,
            'fetched_at' => $lastSuccess['fetched_at'] ?? null,
            'last_checked_at' => is_int($lastChecked)
                ? Carbon::createFromTimestamp($lastChecked)->toIso8601String()
                : null,
            'is_stale' => (bool) Cache::get(self::STALE_KEY, $lastSuccess === null),
            'is_available' => $lastSuccess !== null && ! in_array($health, ['provider_error', 'insufficient'], true),
            'health' => $health,
            'error_code' => Cache::get(self::ERROR_CODE_KEY),
            'error_message' => $this->sanitizedErrorMessage(Cache::get(self::ERROR_CODE_KEY)),
        ];
    }

    private function storeHealth(string $health, string $code): void
    {
        try {
            Cache::put(self::STALE_KEY, true, $this->retentionSeconds());
            Cache::put(self::HEALTH_KEY, $health, $this->retentionSeconds());
            Cache::put(self::ERROR_CODE_KEY, $code, $this->retentionSeconds());
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');
        }
    }

    private function clearSendError(): void
    {
        try {
            $lastSuccess = Cache::get(self::LAST_SUCCESS_KEY);
            $health = is_array($lastSuccess) && is_int($lastSuccess['balance'] ?? null)
                ? $this->healthForBalance($lastSuccess['balance'])
                : 'unknown';

            Cache::put(self::HEALTH_KEY, $health, $this->retentionSeconds());
            Cache::forget(self::ERROR_CODE_KEY);
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');
        }
    }

    private function healthForBalance(int $balance): string
    {
        if ($balance < (int) config('sms.balance_critical_threshold', 1000)) {
            return 'critical';
        }

        if ($balance <= (int) config('sms.balance_warning_threshold', 5000)) {
            return 'warning';
        }

        return 'healthy';
    }

    private function sanitizeCode(mixed $code): ?string
    {
        if (! is_string($code) && ! is_int($code)) {
            return null;
        }

        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z0-9_-]{1,32}$/', $code) === 1 ? $code : 'INVALID_RESPONSE';
    }

    private function sanitizedErrorMessage(mixed $code): ?string
    {
        return match ($code) {
            'F0006' => 'Invalid provider account.',
            'F0007' => 'Provider account is closed.',
            'F0008' => 'Provider signature was rejected.',
            'F0014' => 'Provider IP whitelist rejected the request.',
            'F0015' => 'Provider reports insufficient balance.',
            'F0017' => 'No SMS business is available.',
            null => null,
            default => 'SMS provider is temporarily unavailable.',
        };
    }

    private function retentionSeconds(): int
    {
        return (int) config('sms.balance_last_known_seconds', 86400);
    }

    private function unavailableState(): array
    {
        return [
            'balance' => null,
            'provider_balance_time' => null,
            'fetched_at' => null,
            'last_checked_at' => null,
            'is_stale' => true,
            'is_available' => false,
            'health' => 'provider_error',
            'error_code' => null,
            'error_message' => 'SMS provider is temporarily unavailable.',
        ];
    }
}
