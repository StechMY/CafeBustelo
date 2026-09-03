<?php

use App\Services\Sms\SmsBalanceService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
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
        $this->balance = is_int($state['balance'] ?? null) || is_float($state['balance'] ?? null)
            ? (float) $state['balance']
            : null;
        $this->isStale = (bool) ($state['is_stale'] ?? true);
        $this->isAvailable = (bool) ($state['is_available'] ?? false);
        $this->health = is_string($state['health'] ?? null) ? $state['health'] : 'unknown';
        $this->fetchedAt = null;

        if (is_string($state['fetched_at'] ?? null)) {
            try {
                $this->fetchedAt = Carbon::parse($state['fetched_at'])
                    ->timezone(config('app.timezone'))
                    ->format('Y-m-d H:i');
            } catch (Throwable) {
                $this->fetchedAt = null;
            }
        }
    }
};

?>

@php
    $color = match (true) {
        $health === 'insufficient', $health === 'critical' => 'danger',
        $health === 'warning', $health === 'provider_error', $isStale => 'warning',
        $health === 'healthy' => 'success',
        default => 'gray',
    };

    $tooltip = match (true) {
        $health === 'insufficient' => __('filament.sms_balance_insufficient'),
        $health === 'critical' => __('filament.sms_balance_critical'),
        $health === 'warning' => __('filament.sms_balance_low'),
        $balance !== null && (! $isAvailable || $isStale) => __('filament.sms_last_known_balance', ['time' => $fetchedAt ?? '-']),
        $health === 'provider_error' => __('filament.sms_provider_error'),
        $balance === null => __('filament.sms_balance_unavailable'),
        default => __('filament.sms_balance_updated_at', ['time' => $fetchedAt ?? '-']),
    };

    $formattedBalance = $balance === null
        ? null
        : rtrim(rtrim(number_format($balance, 4, '.', ','), '0'), '.');
@endphp

<div wire:poll.300s="pollBalance" class="flex shrink-0 items-center gap-1">
    <x-filament::badge
        :color="$color"
        icon="heroicon-o-chat-bubble-left-right"
        size="sm"
        :tooltip="$tooltip"
        class="whitespace-nowrap"
    >
        <span class="hidden xl:inline">{{ __('filament.sms_balance') }}</span>

        @if ($health === 'insufficient')
            <span class="hidden md:inline">{{ __('filament.sms_balance_insufficient') }}</span>
            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-3.5 md:hidden" />
        @elseif ($balance !== null)
            <span>{{ $formattedBalance }}</span>
            <span class="hidden lg:inline">{{ __('filament.sms_messages_remaining') }}</span>

            @if (! $isAvailable || $isStale)
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="size-3.5" />
            @endif
        @else
            <span class="hidden md:inline">{{ __('filament.sms_balance_unavailable') }}</span>
            <span class="md:hidden">--</span>
        @endif
    </x-filament::badge>

    <x-filament::icon-button
        icon="heroicon-o-arrow-path"
        color="gray"
        size="sm"
        wire:click="refreshBalance"
        wire:target="refreshBalance"
        :label="__('filament.sms_balance_refresh')"
        :tooltip="__('filament.sms_balance_refresh')"
    />

    <span wire:loading wire:target="refreshBalance" class="fi-sr-only">
        {{ __('filament.sms_balance_refreshing') }}
    </span>
</div>
