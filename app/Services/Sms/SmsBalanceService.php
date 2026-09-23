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

    public function cacheKey(string $key): string
    {
        $account = hash('sha256', rtrim((string) config('sms.base_url'), '/').'|'.config('sms.org_code'));

        return 'abosend:'.$account.':'.$key;
    }

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
                $this->cacheKey(self::MANUAL_COOLDOWN_KEY),
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
            Cache::put($this->cacheKey(self::STALE_KEY), true, $this->retentionSeconds());
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');
        }
    }

    public function isStale(): bool
    {
        try {
            return (bool) Cache::get($this->cacheKey(self::STALE_KEY), true);
        } catch (Throwable) {
            return true;
        }
    }

    public function lastKnownBalance(): ?array
    {
        try {
            $balance = Cache::get($this->cacheKey(self::LAST_SUCCESS_KEY));

            return is_array($balance) ? $balance : null;
        } catch (Throwable) {
            return null;
        }
    }

    public function recordSendResult(?string $code): void
    {
        $code = $this->sanitizeCode($code);

        if ($code === '200') {
            $this->markStale();
            $this->clearSendError();

            return;
        }

        // Submission errors are distinct from delivery status codes. Until a
        // documented balance-error code is available, do not guess its meaning.
        $this->storeHealth('provider_error', $code ?? 'INVALID_RESPONSE');
    }

    private function refreshWithLock(bool $force = false): array
    {
        $result = Cache::lock($this->cacheKey(self::REFRESH_LOCK_KEY), 20)->get(function () use ($force): array {
            $lastChecked = Cache::get($this->cacheKey(self::LAST_CHECKED_KEY));
            if (is_int($lastChecked) && now()->timestamp - $lastChecked < 5) {
                return $this->currentState();
            }

            if (! $force && ! $this->shouldRefresh()) {
                return $this->currentState();
            }

            return $this->fetchProviderBalance();
        });

        return is_array($result) ? $result : $this->currentState();
    }

    private function shouldRefresh(): bool
    {
        $lastChecked = Cache::get($this->cacheKey(self::LAST_CHECKED_KEY));

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
        $orgCode = (string) config('sms.org_code');
        $md5Key = (string) config('sms.md5_key');

        if (! config('sms.enabled') || blank($orgCode) || blank($md5Key) || blank(config('sms.base_url'))) {
            return $this->rememberFailure('CONFIGURATION');
        }

        $rand = (string) random_int(100000, 999999);
        $sign = strtoupper(md5($orgCode.$rand.$md5Key));
        $httpStatus = null;

        try {
            $response = Http::connectTimeout(5)->timeout(10)->asForm()->post(
                rtrim((string) config('sms.base_url'), '/').'/api/viewOrgBalance',
                [
                    'orgCode' => $orgCode,
                    'rand' => $rand,
                    'sign' => $sign,
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

            if (! in_array($data['code'] ?? null, [200, '200'], true)) {
                return $this->rememberFailure($providerCode ?? 'INVALID_RESPONSE', $httpStatus);
            }

            if (data_get($data, 'data.orgCode') !== $orgCode) {
                return $this->rememberFailure('INVALID_RESPONSE', $httpStatus);
            }

            $rawBalance = data_get($data, 'data.balance');
            $balance = filter_var($rawBalance, FILTER_VALIDATE_FLOAT);

            if ((! is_string($rawBalance) && ! is_int($rawBalance) && ! is_float($rawBalance)) || $balance === false || ! is_finite($balance) || $balance < 0) {
                return $this->rememberFailure('INVALID_RESPONSE', $httpStatus);
            }

            $fetchedAt = now()->toIso8601String();
            $lastSuccess = [
                'balance' => $balance,
                'provider_balance_time' => null,
                'fetched_at' => $fetchedAt,
            ];

            Cache::put($this->cacheKey(self::LAST_SUCCESS_KEY), $lastSuccess, $this->retentionSeconds());
            Cache::put($this->cacheKey(self::LAST_CHECKED_KEY), now()->timestamp, $this->retentionSeconds());
            Cache::put($this->cacheKey(self::STALE_KEY), false, $this->retentionSeconds());
            Cache::put($this->cacheKey(self::HEALTH_KEY), $this->healthForBalance($balance), $this->retentionSeconds());
            Cache::forget($this->cacheKey(self::ERROR_CODE_KEY));

            return $this->currentState();
        } catch (Throwable) {
            return $this->rememberFailure('REQUEST_FAILED', $httpStatus);
        }
    }

    private function rememberFailure(?string $code, ?int $httpStatus = null): array
    {
        $code = $this->sanitizeCode($code) ?? 'PROVIDER_ERROR';
        $health = 'provider_error';

        Cache::put($this->cacheKey(self::LAST_CHECKED_KEY), now()->timestamp, $this->retentionSeconds());
        Cache::put($this->cacheKey(self::STALE_KEY), true, $this->retentionSeconds());
        Cache::put($this->cacheKey(self::HEALTH_KEY), $health, $this->retentionSeconds());
        Cache::put($this->cacheKey(self::ERROR_CODE_KEY), $code, $this->retentionSeconds());

        Log::warning('SMS balance lookup failed.', array_filter([
            'code' => $code,
            'http_status' => $httpStatus,
        ], fn (mixed $value): bool => $value !== null));

        return $this->currentState();
    }

    private function currentState(): array
    {
        $lastSuccess = Cache::get($this->cacheKey(self::LAST_SUCCESS_KEY));

        if (
            ! is_array($lastSuccess)
            || (! is_int($lastSuccess['balance'] ?? null) && ! is_float($lastSuccess['balance'] ?? null))
            || ! is_finite((float) $lastSuccess['balance'])
            || $lastSuccess['balance'] < 0
        ) {
            $lastSuccess = null;
        } else {
            $lastSuccess['balance'] = (float) $lastSuccess['balance'];
        }

        $health = (string) Cache::get(
            $this->cacheKey(self::HEALTH_KEY),
            $lastSuccess ? $this->healthForBalance($lastSuccess['balance']) : 'unknown',
        );
        $lastChecked = Cache::get($this->cacheKey(self::LAST_CHECKED_KEY));

        return [
            'balance' => $lastSuccess['balance'] ?? null,
            'provider_balance_time' => $lastSuccess['provider_balance_time'] ?? null,
            'fetched_at' => $lastSuccess['fetched_at'] ?? null,
            'last_checked_at' => is_int($lastChecked)
                ? Carbon::createFromTimestamp($lastChecked)->toIso8601String()
                : null,
            'is_stale' => (bool) Cache::get($this->cacheKey(self::STALE_KEY), $lastSuccess === null),
            'is_available' => $lastSuccess !== null && ! in_array($health, ['provider_error', 'insufficient'], true),
            'health' => $health,
            'error_code' => Cache::get($this->cacheKey(self::ERROR_CODE_KEY)),
            'error_message' => $this->sanitizedErrorMessage(Cache::get($this->cacheKey(self::ERROR_CODE_KEY))),
        ];
    }

    private function storeHealth(string $health, string $code): void
    {
        try {
            Cache::put($this->cacheKey(self::STALE_KEY), true, $this->retentionSeconds());
            Cache::put($this->cacheKey(self::HEALTH_KEY), $health, $this->retentionSeconds());
            Cache::put($this->cacheKey(self::ERROR_CODE_KEY), $code, $this->retentionSeconds());
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');
        }
    }

    private function clearSendError(): void
    {
        try {
            $lastSuccess = Cache::get($this->cacheKey(self::LAST_SUCCESS_KEY));
            $health = is_array($lastSuccess) && (is_int($lastSuccess['balance'] ?? null) || is_float($lastSuccess['balance'] ?? null))
                ? $this->healthForBalance((float) $lastSuccess['balance'])
                : 'unknown';

            Cache::put($this->cacheKey(self::HEALTH_KEY), $health, $this->retentionSeconds());
            Cache::forget($this->cacheKey(self::ERROR_CODE_KEY));
        } catch (Throwable) {
            Log::warning('SMS balance cache is unavailable.');
        }
    }

    private function healthForBalance(float $balance): string
    {
        $critical = config('sms.balance_critical_threshold');
        $warning = config('sms.balance_warning_threshold');

        if ($balance == 0 || (is_numeric($critical) && $balance <= (float) $critical)) {
            return 'critical';
        }

        if (is_numeric($warning) && $balance <= (float) $warning) {
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
            'CONFIGURATION' => 'AboSEND is not configured.',
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
