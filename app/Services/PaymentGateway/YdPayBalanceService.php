<?php

namespace App\Services\PaymentGateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class YdPayBalanceService
{
    public const LAST_SUCCESS_KEY = 'ydpay_available_balance:last_success';

    public const LAST_CHECK_KEY = 'ydpay_available_balance:last_check';

    public const REFRESH_LOCK_KEY = 'ydpay_available_balance:refresh_lock';

    public const MANUAL_COOLDOWN_KEY = 'ydpay_available_balance:manual_cooldown';

    public function __construct(private readonly YdPayGateway $gateway) {}

    public function getCachedBalance(): array
    {
        if ($state = $this->availabilityState()) {
            return $state;
        }

        try {
            return $this->currentState();
        } catch (Throwable) {
            return $this->unavailableState('provider_error');
        }
    }

    public function getBalance(): array
    {
        return $this->refreshWithLock();
    }

    public function refresh(): array
    {
        return $this->refreshWithLock(force: true);
    }

    private function refreshWithLock(bool $force = false): array
    {
        if ($state = $this->availabilityState()) {
            return $state;
        }

        try {
            if ((! $force && Cache::has(self::LAST_CHECK_KEY))
                || ($force && ! Cache::add(self::MANUAL_COOLDOWN_KEY, true, 10))) {
                return $this->currentState();
            }

            // Outlast the gateway's 20-second timeout so overlapping refreshes cannot fan out.
            $result = Cache::lock(self::REFRESH_LOCK_KEY, 30)->get(function () use ($force): array {
                if (! $force && Cache::has(self::LAST_CHECK_KEY)) {
                    return $this->currentState();
                }

                try {
                    $response = $this->gateway->queryBalance();
                    $minor = $response['availableAgentpayBalance'] ?? null;
                    if ((! is_string($minor) && ! is_int($minor)) || ! preg_match('/^\d+$/D', (string) $minor)) {
                        throw new RuntimeException('Invalid YDPAY available balance.');
                    }

                    // Provider units are VND x100; keep the conversion exact, including zero.
                    $minor = str_pad(ltrim((string) $minor, '0'), 3, '0', STR_PAD_LEFT);
                    Cache::put(self::LAST_SUCCESS_KEY, [
                        'balance' => substr($minor, 0, -2).'.'.substr($minor, -2),
                        'fetched_at' => now()->toIso8601String(),
                    ], 86400);
                    Cache::put(self::LAST_CHECK_KEY, ['failed' => false], 300);
                } catch (Throwable) {
                    Cache::put(self::LAST_CHECK_KEY, ['failed' => true], 30);
                    Log::warning('YDPAY available balance lookup failed.');
                }

                return $this->currentState();
            });

            return is_array($result) ? $result : $this->currentState();
        } catch (Throwable) {
            return $this->unavailableState('provider_error');
        }
    }

    private function currentState(): array
    {
        $lastSuccess = Cache::get(self::LAST_SUCCESS_KEY);
        $lastCheck = Cache::get(self::LAST_CHECK_KEY);
        $failed = $lastCheck['failed'] ?? false;
        $balance = $lastSuccess['balance'] ?? null;

        return [
            'currency' => 'vnd',
            'balance' => $balance,
            'fetched_at' => $lastSuccess['fetched_at'] ?? null,
            'is_stale' => $failed || $lastCheck === null || $balance === null,
            'is_available' => ! $failed && $balance !== null,
            'health' => $failed ? 'provider_error' : ($balance === null ? 'unknown' : ($balance === '0.00' ? 'danger' : 'healthy')),
        ];
    }

    private function availabilityState(): ?array
    {
        if (! config('services.ydpay.enabled')) {
            return $this->unavailableState('disabled');
        }
        if (! filled(config('services.ydpay.merchant_id')) || ! filled(config('services.ydpay.secret'))
            || ! filled(config('third_party.outbound_proxy'))) {
            return $this->unavailableState('configuration_incomplete');
        }

        return null;
    }

    private function unavailableState(string $health): array
    {
        return ['currency' => 'vnd', 'balance' => null, 'fetched_at' => null,
            'is_stale' => true, 'is_available' => false, 'health' => $health];
    }
}
