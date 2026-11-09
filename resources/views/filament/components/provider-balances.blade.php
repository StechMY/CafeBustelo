@include('filament.components.provider-balance-styles')

<style>
    .admin-provider-balances {
        display: inline-flex;
        align-items: center;
        flex: 0 1 auto;
        gap: 8px;
        min-width: 0;
    }

    .admin-provider-balances > [data-balance-provider] {
        display: inline-flex;
        min-width: 0;
    }

    .gateway-balance-compact {
        display: none;
    }

    @media (max-width: 1023px) {
        .admin-provider-balances {
            gap: 5px;
        }

        .gateway-balance-full {
            display: none;
        }

        .gateway-balance-compact {
            display: inline-block;
        }
    }

    @media (max-width: 640px) {
        .fi-topbar-ctn:has(.admin-provider-balances),
        .fi-topbar:has(.admin-provider-balances) {
            height: 104px;
            min-height: 104px;
        }

        .fi-topbar:has(.admin-provider-balances) {
            align-items: flex-start;
            padding-top: 14px;
        }

        .fi-topbar-end:has(.admin-provider-balances) {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            grid-template-rows: 36px 32px;
            align-items: center;
            column-gap: 12px;
            row-gap: 8px;
            height: 76px;
        }

        .fi-topbar-end:has(.admin-provider-balances) > .fi-global-search-ctn {
            grid-column: 1;
            grid-row: 1;
            width: 100%;
        }

        .fi-topbar-end:has(.admin-provider-balances) > .fi-user-menu {
            grid-column: 3;
            grid-row: 1;
        }

        .admin-provider-balances {
            grid-column: 1 / -1;
            grid-row: 2;
            min-width: max-content;
        }

        .admin-provider-balances > [data-balance-provider] {
            flex-shrink: 0;
            min-width: max-content;
        }
    }
</style>

<div class="admin-provider-balances">
    <div data-balance-provider="sms">
        <livewire:admin-sms-balance-indicator />
    </div>

    <div data-balance-provider="{{ config('services.ydpay.enabled') ? 'ydpay' : 'qc168' }}">
        <livewire:admin-payment-gateway-balance-indicator />
    </div>
</div>
