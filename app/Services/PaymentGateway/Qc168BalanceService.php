<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class Qc168BalanceService
{
    public const LAST_SUCCESS_KEY = 'qc168_merchant_balance:last_success';

    public const LAST_CHECKED_KEY = 'qc168_merchant_balance:last_checked';

    public const STALE_KEY = 'qc168_merchant_balance:stale';

    public const HEALTH_KEY = 'qc168_merchant_balance:health';

    public const ERROR_CODE_KEY = 'qc168_merchant_balance:error_code';

    public const REFRESH_LOCK_KEY = 'qc168_merchant_balance:refresh_lock';

    public const MANUAL_COOLDOWN_KEY = 'qc168_merchant_balance:manual_cooldown';

    private const CACHE_SECONDS = 300;

    private const LAST_KNOWN_SECONDS = 86400;

    private const STALE_RETRY_SECONDS = 30;

    private const REFRESH_LOCK_SECONDS = 20;

    private const MANUAL_COOLDOWN_SECONDS = 10;

    public function __construct(
        private readonly Qc168Gateway $gateway,
    ) {}

    public function getBalance(): array
    {
        if ($state = $this->availabilityState()) {
            return $state;
        }

        try {
            if (! $this->shouldRefresh()) {
                return $this->currentState();
            }

            return $this->refreshWithLock();
        } catch (Throwable) {
            Log::warning('QC168 merchant balance cache is unavailable.');

            return $this->unavailableState('provider_error');
        }
    }

    public function refresh(): array
    {
        if ($state = $this->availabilityState()) {
            return $state;
        }

        try {
            if (! Cache::add(
                self::MANUAL_COOLDOWN_KEY,
                true,
                self::MANUAL_COOLDOWN_SECONDS,
            )) {
                return $this->currentState();
            }

            return $this->refreshWithLock(force: true);
        } catch (Throwable) {
            Log::warning('QC168 merchant balance cache is unavailable.');

            return $this->unavailableState('provider_error');
        }
    }

    public function markStale(): void
    {
        try {
            Cache::put(self::STALE_KEY, true, self::LAST_KNOWN_SECONDS);
        } catch (Throwable) {
            Log::warning('QC168 merchant balance cache is unavailable.');
        }
    }

    private function refreshWithLock(bool $force = false): array
    {
        $result = Cache::lock(self::REFRESH_LOCK_KEY, self::REFRESH_LOCK_SECONDS)
            ->get(function () use ($force): array {
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
        $interval = Cache::get(self::STALE_KEY, true)
            ? self::STALE_RETRY_SECONDS
            : self::CACHE_SECONDS;

        return $age >= $interval;
    }

    private function fetchProviderBalance(): array
    {
        try {
            $response = $this->gateway->getMerchantLedger();
            $data = $response['data'] ?? null;
            $currency = $this->configuredCurrency();

            if (! is_array($data) || ! array_key_exists($currency, $data)) {
                return $this->rememberFailure('MISSING_CURRENCY');
            }

            $balance = $this->decimalString($data[$currency]);

            if ($balance === null) {
                return $this->rememberFailure('INVALID_BALANCE');
            }

            $lastSuccess = [
                'merchant_name' => $this->merchantName($data['merchant_name'] ?? null),
                'currency' => $currency,
                'balance' => $balance,
                'fetched_at' => now()->toIso8601String(),
            ];

            Cache::put(self::LAST_SUCCESS_KEY, $lastSuccess, self::LAST_KNOWN_SECONDS);
            Cache::put(self::LAST_CHECKED_KEY, now()->timestamp, self::LAST_KNOWN_SECONDS);
            Cache::put(self::STALE_KEY, false, self::LAST_KNOWN_SECONDS);
            Cache::put(self::HEALTH_KEY, $this->healthForBalance($balance), self::LAST_KNOWN_SECONDS);
            Cache::forget(self::ERROR_CODE_KEY);

            return $this->currentState();
        } catch (Throwable) {
            return $this->rememberFailure('REQUEST_FAILED');
        }
    }

    private function rememberFailure(string $code): array
    {
        Cache::put(self::LAST_CHECKED_KEY, now()->timestamp, self::LAST_KNOWN_SECONDS);
        Cache::put(self::STALE_KEY, true, self::LAST_KNOWN_SECONDS);
        Cache::put(self::HEALTH_KEY, 'provider_error', self::LAST_KNOWN_SECONDS);
        Cache::put(self::ERROR_CODE_KEY, $code, self::LAST_KNOWN_SECONDS);

        Log::warning('QC168 merchant balance lookup failed.', ['code' => $code]);

        return $this->currentState();
    }

    private function currentState(): array
    {
        $lastSuccess = Cache::get(self::LAST_SUCCESS_KEY);

        if (! is_array($lastSuccess)
            || $this->decimalString($lastSuccess['balance'] ?? null) === null
            || ! is_string($lastSuccess['currency'] ?? null)
        ) {
            $lastSuccess = null;
        }

        $health = (string) Cache::get(
            self::HEALTH_KEY,
            $lastSuccess ? $this->healthForBalance($lastSuccess['balance']) : 'unknown',
        );
        $lastChecked = Cache::get(self::LAST_CHECKED_KEY);

        return [
            'merchant_name' => $lastSuccess['merchant_name'] ?? null,
            'currency' => $lastSuccess['currency'] ?? $this->configuredCurrency(),
            'balance' => $lastSuccess['balance'] ?? null,
            'fetched_at' => $lastSuccess['fetched_at'] ?? null,
            'last_checked_at' => is_int($lastChecked)
                ? Carbon::createFromTimestamp($lastChecked)->toIso8601String()
                : null,
            'is_stale' => (bool) Cache::get(self::STALE_KEY, $lastSuccess === null),
            'is_available' => $lastSuccess !== null && in_array($health, ['healthy', 'danger'], true),
            'health' => $health,
            'error_code' => Cache::get(self::ERROR_CODE_KEY),
        ];
    }

    private function availabilityState(): ?array
    {
        if (! $this->gateway->isEnabled()) {
            return $this->unavailableState('disabled');
        }

        if (! $this->gateway->canQueryMerchantBalance()) {
            return $this->unavailableState('configuration_incomplete');
        }

        return null;
    }

    private function unavailableState(string $health): array
    {
        return [
            'merchant_name' => null,
            'currency' => $this->configuredCurrency(),
            'balance' => null,
            'fetched_at' => null,
            'last_checked_at' => null,
            'is_stale' => true,
            'is_available' => false,
            'health' => $health,
            'error_code' => null,
        ];
    }

    private function configuredCurrency(): string
    {
        $currency = strtolower(trim((string) config('services.qc168.currency', 'vnd')));

        return $currency !== '' ? $currency : 'vnd';
    }

    private function decimalString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^\d+(?:\.\d+)?$/', $value) === 1 ? $value : null;
    }

    private function merchantName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? mb_substr($value, 0, 200) : null;
    }

    private function healthForBalance(string $balance): string
    {
        return preg_match('/^0+(?:\.0+)?$/', $balance) === 1 ? 'danger' : 'healthy';
    }
}
