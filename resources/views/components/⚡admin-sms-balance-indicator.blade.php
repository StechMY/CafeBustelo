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
