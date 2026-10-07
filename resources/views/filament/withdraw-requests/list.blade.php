<x-filament-panels::page>
    <style>
        .withdraw-requests-table .withdraw-customer-cell { min-width: 8rem; }
        .withdraw-requests-table .withdraw-amount-cell { min-width: 9rem; max-width: 12rem; }
        .withdraw-requests-table .withdraw-status-cell { min-width: 10rem; max-width: 13rem; }
        .withdraw-requests-table .withdraw-handling-cell { min-width: 12rem; max-width: 17rem; }
        .withdraw-requests-table .withdraw-bank-cell { min-width: 10rem; max-width: 14rem; }

        .withdraw-requests-table .fi-ta-text:not(.fi-inline),
        .withdraw-requests-table .fi-ta-header-cell {
            padding-inline: 0.625rem;
        }

        .withdraw-requests-table .fi-ta-actions-header-cell,
        .withdraw-requests-table .fi-ta-cell:has(> .fi-ta-actions) {
            position: sticky;
            inset-inline-end: 0;
            z-index: 2;
            background-color: white;
            box-shadow: -4px 0 8px -4px rgb(0 0 0 / 20%);
        }

        .withdraw-requests-table .fi-ta-actions-header-cell { background-color: #f9fafb; }

        .dark .withdraw-requests-table .fi-ta-cell:has(> .fi-ta-actions) {
            background-color: var(--gray-900, #18181b);
        }

        .dark .withdraw-requests-table .fi-ta-actions-header-cell {
            background-color: var(--gray-800, #27272a);
        }

        .withdraw-requests-table .fi-ta-cell > .fi-ta-actions {
            flex-direction: column;
            align-items: flex-end;
            gap: 0.625rem;
        }
    </style>

    {{ $this->content }}
</x-filament-panels::page>
