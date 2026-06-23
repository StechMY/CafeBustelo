<div @class([
    'ops-cell',
    'ops-cell-empty' => $item['empty'] ?? false,
    'ops-highlight' => $item['highlight'] ?? false,
    'ops-tone-blue' => ($item['tone'] ?? 'blue') === 'blue',
    'ops-tone-green' => ($item['tone'] ?? 'blue') === 'green',
    'ops-tone-red' => ($item['tone'] ?? 'blue') === 'red',
    'ops-tone-yellow' => ($item['tone'] ?? 'blue') === 'yellow',
    'ops-tone-purple' => ($item['tone'] ?? 'blue') === 'purple',
    'ops-tone-gray' => ($item['tone'] ?? 'blue') === 'gray',
])>
    @unless ($item['empty'] ?? false)
        <div class="ops-icon">
            @if (($item['icon'] ?? null) === 'users')
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 0 0-3-3.87M9 20H4v-2a4 4 0 0 1 3-3.87m10-4.13a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm-8 0a4 4 0 1 0-8 0 4 4 0 0 0 8 0Z" />
                </svg>
            @elseif (($item['icon'] ?? null) === 'orders')
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6m-6 4h6m-6 4h6m-9 8h12a2 2 0 0 0 2-2V7.5L15.5 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z" />
                </svg>
            @elseif (($item['icon'] ?? null) === 'chart')
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m4 14v-7m4 7V8m4 11v-4m4 4V4" />
                </svg>
            @elseif (($item['icon'] ?? null) === 'gift')
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7m16 0H4m16 0V8a2 2 0 0 0-2-2h-3.5M4 12V8a2 2 0 0 1 2-2h3.5m0 0C8 4.5 8.5 2 10.5 2S12 6 12 6m-2.5 0H12m0 0s-.5-4 1.5-4S16 4.5 14.5 6H12m0 0v15" />
                </svg>
            @else
                $
            @endif
        </div>

        <div class="ops-content">
            <div class="ops-value">
                {{ $item['value'] }}
            </div>

            <div class="ops-label">
                {{ $item['label'] }}
            </div>

            @if (! empty($item['desc']))
                <div class="ops-mobile-desc">
                    {{ $item['desc'] }}
                </div>
            @endif
        </div>

        @if (! empty($item['desc']))
            <div class="ops-desc-tooltip">
                {{ $item['desc'] }}
            </div>
        @endif
    @endunless
</div>