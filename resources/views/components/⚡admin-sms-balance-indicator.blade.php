<?php

use App\Services\Sms\SmsBalanceService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public ?float $balance = null;

    #[Locked]
    public ?string $fetchedAt = null;

    #[Locked]
    public bool $isStale = true;

    #[Locked]
    public bool $isAvailable = false;

    #[Locked]
    public string $health = 'unknown';

    public function mount(SmsBalanceService $balanceService): void
    {
        $this->applyState($balanceService->getBalance());
    }

    public function pollBalance(SmsBalanceService $balanceService): void
    {
        $this->applyState($balanceService->getBalance());
    }

    public function refreshBalance(SmsBalanceService $balanceService): void
    {
        $this->applyState($balanceService->refresh());
    }

    private function applyState(array $state): void
    {
        $balance = $state['balance'] ?? null;

        $this->balance = $balance;

        $this->isStale = (bool) ($state['is_stale'] ?? true);

        $this->isAvailable = (bool) ($state['is_available'] ?? false);

        $this->health = is_string($state['health'] ?? null) ? $state['health'] : 'unknown';

        $this->fetchedAt = null;

        if (!empty($state['fetched_at']) && is_string($state['fetched_at'])) {
            try {
                $this->fetchedAt = Carbon::parse($state['fetched_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i');
            } catch (\Throwable $e) {
                $this->fetchedAt = null;
            }
        }
    }
};

?>

@php
    $status = match (true) {
        in_array($health, ['insufficient', 'critical'], true) => 'danger',

        in_array($health, ['warning', 'provider_error'], true), $isStale => 'warning',

        $health === 'healthy' => 'success',

        default => 'gray',
    };

    $tooltip = match (true) {
        $health === 'insufficient' => __('filament.sms_balance_insufficient'),

        $health === 'critical' => __('filament.sms_balance_critical'),

        $health === 'warning' => __('filament.sms_balance_low'),

        $balance !== null && (!$isAvailable || $isStale) => __('filament.sms_last_known_balance', [
            'time' => $fetchedAt ?? '-',
        ]),

        $health === 'provider_error' => __('filament.sms_provider_error'),

        $balance === null => __('filament.sms_balance_unavailable'),

        default => __('filament.sms_balance_updated_at', [
            'time' => $fetchedAt ?? '-',
        ]),
    };
@endphp


<style>
    /*
    |--------------------------------------------------------------------------
    | SMS Balance Topbar
    |--------------------------------------------------------------------------
    |
    | Intentionally uses plain CSS instead of Tailwind utilities.
    | This makes the widget independent from Filament theme compilation.
    |
    */

    .sms-balance-root {
        display: inline-flex !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        flex-shrink: 0 !important;

        width: auto !important;
        min-width: max-content !important;
        max-width: none !important;

        margin: 0 !important;
        padding: 0 !important;

        line-height: 1 !important;
        vertical-align: middle;
    }


    .sms-balance-control {
        display: inline-flex !important;
        align-items: center !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;

        width: auto !important;
        min-width: max-content !important;

        height: 34px;

        overflow: hidden;

        border: 1px solid;
        border-radius: 9px;

        box-sizing: border-box;

        font-size: 13px;
        font-weight: 500;
        line-height: 1;

        white-space: nowrap;

        transition:
            background-color 150ms ease,
            border-color 150ms ease,
            color 150ms ease;
    }


    /*
    |--------------------------------------------------------------------------
    | Status colours
    |--------------------------------------------------------------------------
    */

    .sms-balance-control[data-status="success"] {
        background: #ecfdf3;
        border-color: #bbf7d0;
        color: #15803d;
    }

    .sms-balance-control[data-status="warning"] {
        background: #fffbeb;
        border-color: #fde68a;
        color: #b45309;
    }

    .sms-balance-control[data-status="danger"] {
        background: #fef2f2;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .sms-balance-control[data-status="gray"] {
        background: #ffffff;
        border-color: #e5e7eb;
        color: #4b5563;
    }


    /*
    |--------------------------------------------------------------------------
    | Main balance area
    |--------------------------------------------------------------------------
    */

    .sms-balance-main {
        display: inline-flex !important;
        align-items: center !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;

        gap: 6px;

        height: 100%;

        padding: 0 10px;

        white-space: nowrap;
    }


    .sms-balance-icon {
        display: block !important;

        width: 16px !important;
        height: 16px !important;

        min-width: 16px !important;

        flex: 0 0 16px !important;
    }


    .sms-balance-label {
        display: inline-block;
        white-space: nowrap;
    }


    .sms-balance-number {
        display: inline-block;

        font-weight: 700;

        font-variant-numeric: tabular-nums;

        white-space: nowrap;
    }


    .sms-balance-unit {
        display: inline-block;
        white-space: nowrap;
    }


    .sms-balance-warning-icon {
        display: block !important;

        width: 15px !important;
        height: 15px !important;

        min-width: 15px !important;

        flex: 0 0 15px !important;
    }


    /*
    |--------------------------------------------------------------------------
    | Divider
    |--------------------------------------------------------------------------
    */

    .sms-balance-divider {
        display: block !important;

        width: 1px !important;
        min-width: 1px !important;

        height: 18px;

        flex: 0 0 1px !important;

        opacity: .25;

        background: currentColor;
    }


    /*
    |--------------------------------------------------------------------------
    | Refresh button
    |--------------------------------------------------------------------------
    */

    .sms-balance-refresh {
        appearance: none;
        -webkit-appearance: none;

        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;

        flex: 0 0 34px !important;

        width: 34px !important;
        min-width: 34px !important;

        height: 100% !important;

        margin: 0 !important;
        padding: 0 !important;

        border: 0 !important;
        border-radius: 0 !important;

        background: transparent !important;
        color: inherit !important;

        cursor: pointer;

        outline: none;

        transition: background-color 150ms ease;
    }


    .sms-balance-refresh:hover {
        background: rgba(0, 0, 0, .05) !important;
    }


    .sms-balance-refresh:focus-visible {
        box-shadow: inset 0 0 0 2px currentColor;
    }


    .sms-balance-refresh:disabled {
        cursor: default;
        opacity: .55;
    }


    .sms-balance-refresh-icon {
        display: block !important;

        width: 16px !important;
        height: 16px !important;
    }

    .sms-balance-refresh>span {
        align-items: center;
        justify-content: center;
    }


    /*
    |--------------------------------------------------------------------------
    | Spinner
    |--------------------------------------------------------------------------
    */

    .sms-balance-spinner {
        width: 15px;
        height: 15px;

        box-sizing: border-box;

        border: 2px solid currentColor;
        border-right-color: transparent;

        border-radius: 9999px;

        animation: sms-balance-spin .7s linear infinite;
    }


    @keyframes sms-balance-spin {
        to {
            transform: rotate(360deg);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Responsive
    |--------------------------------------------------------------------------
    */

    @media (max-width: 1279px) {
        .sms-balance-label {
            display: none;
        }
    }


    @media (max-width: 1023px) {
        .sms-balance-unit {
            display: none;
        }

        .sms-balance-main {
            padding-left: 9px;
            padding-right: 9px;
        }
    }


    @media (max-width: 640px) {
        .sms-balance-control {
            height: 32px;
            font-size: 12px;
        }

        .sms-balance-main {
            gap: 5px;

            padding-left: 8px;
            padding-right: 8px;
        }

        .sms-balance-refresh {
            width: 31px !important;
            min-width: 31px !important;

            flex-basis: 31px !important;
        }

        .sms-balance-icon {
            width: 15px !important;
            height: 15px !important;

            min-width: 15px !important;

            flex-basis: 15px !important;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Filament dark mode
    |--------------------------------------------------------------------------
    */

    .dark .sms-balance-control[data-status="success"] {
        background: rgba(34, 197, 94, .10);
        border-color: rgba(34, 197, 94, .25);
        color: #4ade80;
    }

    .dark .sms-balance-control[data-status="warning"] {
        background: rgba(245, 158, 11, .10);
        border-color: rgba(245, 158, 11, .25);
        color: #fbbf24;
    }

    .dark .sms-balance-control[data-status="danger"] {
        background: rgba(239, 68, 68, .10);
        border-color: rgba(239, 68, 68, .25);
        color: #f87171;
    }

    .dark .sms-balance-control[data-status="gray"] {
        background: rgba(255, 255, 255, .04);
        border-color: rgba(255, 255, 255, .10);
        color: #d1d5db;
    }

    .dark .sms-balance-refresh:hover {
        background: rgba(255, 255, 255, .07) !important;
    }
</style>


<div
     wire:poll.300s="pollBalance"
     class="sms-balance-root">
    <div
         class="sms-balance-control"
         data-status="{{ $status }}"
         title="{{ $tooltip }}">

        {{-- ============================================================= --}}
        {{-- Balance --}}
        {{-- ============================================================= --}}

        <div class="sms-balance-main">

            <x-filament::icon
                              icon="heroicon-o-chat-bubble-left-right"
                              class="sms-balance-icon" />


            <span class="sms-balance-label">
                {{ __('filament.sms_balance') }}
            </span>


            @if ($health === 'insufficient')

                <span class="sms-balance-number">
                    {{ __('filament.sms_balance_insufficient') }}
                </span>

                <x-filament::icon
                                  icon="heroicon-m-exclamation-triangle"
                                  class="sms-balance-warning-icon" />
            @elseif ($balance !== null)
                <span class="sms-balance-number">
                    {{ $balance }}
                </span>


                @if (!$isAvailable || $isStale)
                    <x-filament::icon
                                      icon="heroicon-m-exclamation-triangle"
                                      class="sms-balance-warning-icon" />
                @endif
            @else
                <span class="sms-balance-number">
                    {{ __('filament.sms_balance_unavailable') }}
                </span>

            @endif

        </div>


        {{-- Divider --}}
        <span
              class="sms-balance-divider"
              aria-hidden="true"></span>


        {{-- Refresh --}}
        <button
                type="button"
                class="sms-balance-refresh"

                wire:click="refreshBalance"
                wire:target="refreshBalance"
                wire:loading.attr="disabled"

                title="{{ __('filament.sms_balance_refresh') }}"
                aria-label="{{ __('filament.sms_balance_refresh') }}">
            {{-- Normal --}}
            <span
                  wire:loading.remove
                  wire:target="refreshBalance">
                <x-filament::icon
                                  icon="heroicon-o-arrow-path"
                                  class="sms-balance-refresh-icon" />
            </span>

            {{-- Loading --}}
            <span
                  wire:loading
                  wire:target="refreshBalance">
                <span class="sms-balance-spinner"></span>
            </span>
        </button>

    </div>
</div>
