<?php

use App\Services\PaymentGateway\Qc168BalanceService;
use App\Services\PaymentGateway\YdPayBalanceService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?string $merchantName = null;

    #[Locked]
    public string $currency = 'vnd';

    #[Locked]
    public ?string $balance = null;

    #[Locked]
    public ?string $fetchedAt = null;

    #[Locked]
    public bool $isStale = true;

    #[Locked]
    public bool $isAvailable = false;

    #[Locked]
    public string $health = 'unknown';

    #[Computed]
    public function provider(): string
    {
        return config('services.ydpay.enabled') ? 'ydpay' : 'qc168';
    }

    private function balanceService(): YdPayBalanceService|Qc168BalanceService
    {
        return app($this->provider === 'ydpay' ? YdPayBalanceService::class : Qc168BalanceService::class);
    }

    public function mount(): void
    {
        $this->applyState($this->balanceService()->getCachedBalance());
    }

    public function loadBalance(): void
    {
        $this->applyState($this->balanceService()->getBalance());
    }

    public function pollBalance(): void
    {
        $this->applyState($this->balanceService()->getBalance());
    }

    public function refreshBalance(): void
    {
        $this->applyState($this->balanceService()->refresh());
    }

    private function applyState(array $state): void
    {
        $this->merchantName = is_string($state['merchant_name'] ?? null)
            ? $state['merchant_name']
            : null;
        $this->currency = is_string($state['currency'] ?? null)
            ? $state['currency']
            : 'vnd';
        $this->balance = is_string($state['balance'] ?? null)
            ? $state['balance']
            : null;
        $this->isStale = (bool) ($state['is_stale'] ?? true);
        $this->isAvailable = (bool) ($state['is_available'] ?? false);
        $this->health = is_string($state['health'] ?? null)
            ? $state['health']
            : 'unknown';
        $this->fetchedAt = null;

        if (! empty($state['fetched_at']) && is_string($state['fetched_at'])) {
            try {
                $this->fetchedAt = Carbon::parse($state['fetched_at'])
                    ->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i');
            } catch (\Throwable) {
                $this->fetchedAt = null;
            }
        }
    }
};

?>

@php
    $translationPrefix = 'filament.' . $this->provider . '_';
    $status = match (true) {
        $health === 'danger' => 'danger',
        $health === 'provider_error', $isStale && $balance !== null => 'warning',
        $health === 'healthy' => 'success',
        default => 'gray',
    };

    $tooltip = match (true) {
        $health === 'disabled' => __($translationPrefix . 'gateway_disabled'),
        $health === 'configuration_incomplete' => __($translationPrefix . 'configuration_incomplete'),
        $balance !== null && (!$isAvailable || $isStale) => __($translationPrefix . 'balance_last_known', [
            'time' => $fetchedAt ?? '-',
        ]),
        $health === 'danger' => __($translationPrefix . 'balance_zero'),
        $balance === null => __($translationPrefix . 'balance_unavailable'),
        default => __($translationPrefix . 'balance_updated_at', [
            'time' => $fetchedAt ?? '-',
        ]),
    };

    if ($merchantName !== null) {
        $tooltip .= "\n" . __($translationPrefix . 'merchant') . ': ' . $merchantName;
    }

    $currencyPrefix = match (strtolower($currency)) {
        'vnd' => '₫',
        'usd' => '$',
        'idr' => 'Rp',
        'cny' => '¥',
        'thb' => '฿',
        'php' => '₱',
        'sgd' => 'S$',
        default => strtoupper($currency) . ' ',
    };

    $formatFullBalance = static function (?string $value) use ($currencyPrefix): ?string {
        if ($value === null || preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1) {
            return null;
        }

        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer);

        return $currencyPrefix . $integer . ($fraction !== '' ? '.' . $fraction : '');
    };

    $formatCompactBalance = static function (?string $value) use ($currencyPrefix, $formatFullBalance): ?string {
        if ($value === null || preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1) {
            return null;
        }

        $integer = ltrim(strtok($value, '.'), '0') ?: '0';
        $digits = strlen($integer);
        [$power, $suffix] = match (true) {
            $digits >= 10 => [9, 'B'],
            $digits >= 7 => [6, 'M'],
            $digits >= 4 => [3, 'K'],
            default => [0, ''],
        };

        if ($power === 0) {
            return $formatFullBalance($value);
        }

        $wholeLength = $digits - $power;
        $whole = substr($integer, 0, $wholeLength);
        $decimal = $integer[$wholeLength] ?? '0';

        return $currencyPrefix . $whole . ($decimal !== '0' ? '.' . $decimal : '') . $suffix;
    };

    $fullBalance = $formatFullBalance($balance);
    $compactBalance = $formatCompactBalance($balance);
@endphp

<div wire:init="loadBalance" wire:poll.300s="pollBalance" class="sms-balance-root">
    <div class="sms-balance-control" data-status="{{ $status }}" title="{{ $tooltip }}">
        <div class="sms-balance-main">
            <x-filament::icon icon="heroicon-o-banknotes" class="sms-balance-icon" />

            <span class="sms-balance-label">
                {{ __($translationPrefix . 'balance') }}
            </span>

            @if ($health === 'disabled')
                <span class="sms-balance-number">
                    {{ __($translationPrefix . 'gateway_disabled_short') }}
                </span>
            @elseif ($health === 'configuration_incomplete')
                <span class="sms-balance-number">
                    {{ __($translationPrefix . 'configuration_incomplete_short') }}
                </span>
            @elseif ($fullBalance !== null)
                <span class="sms-balance-number gateway-balance-full">{{ $fullBalance }}</span>
                <span class="sms-balance-number gateway-balance-compact">{{ $compactBalance }}</span>

                @if (! $isAvailable || $isStale)
                    <x-filament::icon
                        icon="heroicon-m-exclamation-triangle"
                        class="sms-balance-warning-icon"
                    />
                @endif
            @else
                <span class="sms-balance-number">
                    {{ __($translationPrefix . 'balance_unavailable') }}
                </span>
            @endif
        </div>

        <span class="sms-balance-divider" aria-hidden="true"></span>

        <button
            type="button"
            class="sms-balance-refresh"
            wire:click="refreshBalance"
            wire:target="refreshBalance"
            wire:loading.attr="disabled"
            title="{{ __($translationPrefix . 'balance_refresh') }}"
            aria-label="{{ __($translationPrefix . 'balance_refresh') }}"
        >
            <span wire:loading.remove wire:target="refreshBalance">
                <x-filament::icon icon="heroicon-o-arrow-path" class="sms-balance-refresh-icon" />
            </span>

            <span wire:loading wire:target="refreshBalance">
                <span class="sms-balance-spinner"></span>
                <span class="sr-only">{{ __($translationPrefix . 'balance_refreshing') }}</span>
            </span>
        </button>
    </div>
</div>
