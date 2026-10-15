<x-filament-panels::page>
    <style>
        @media (max-width: 639px) {
            [data-dashboard-settings-menu] .fi-dropdown-panel {
                left: 1rem !important;
                width: calc(100vw - 2rem);
                max-width: calc(100vw - 2rem) !important;
            }
        }
    </style>

    {{ $this->content }}
</x-filament-panels::page>
