@extends('layouts.app')

@section('title', __('downline_products.title'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');

        $summaryCards = [
            [
                'label' => __('downline_products.total_users'),
                'value' => number_format((int) ($summary['total_users'] ?? 0)),
                'class' => 'bg-[#003b7a] text-white',
            ],
            [
                'label' => __('downline_products.total_products'),
                'value' => number_format((int) ($summary['total_products'] ?? 0)),
                'class' => 'bg-[#e4003a] text-white',
            ],
            [
                'label' => __('downline_products.total_estimated_commission'),
                'value' => $currency . ' ' . number_format((float) ($summary['total_estimated_commission'] ?? 0), 2),
                'class' => 'bg-white text-[#003b7a]',
            ],
        ];

        $levelCards = [
            [
                'label' => 'B',
                'users' => $summary['b_users'] ?? 0,
                'products' => $summary['b_products'] ?? 0,
                'commission' => $summary['b_estimated_commission'] ?? 0,
                'rate' => $commissionRates->get(1)?->rate_percent ?? 0,
                'class' => 'from-[#ff3fac] to-[#e4003a]',
            ],
            [
                'label' => 'C',
                'users' => $summary['c_users'] ?? 0,
                'products' => $summary['c_products'] ?? 0,
                'commission' => $summary['c_estimated_commission'] ?? 0,
                'rate' => $commissionRates->get(2)?->rate_percent ?? 0,
                'class' => 'from-[#22d3c5] to-[#009b8f]',
            ],
            [
                'label' => 'D',
                'users' => $summary['d_users'] ?? 0,
                'products' => $summary['d_products'] ?? 0,
                'commission' => $summary['d_estimated_commission'] ?? 0,
                'rate' => $commissionRates->get(3)?->rate_percent ?? 0,
                'class' => 'from-[#3b82f6] to-[#003b7a]',
            ],
        ];
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <div>
                    <div class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('downline_products.center') }}
                    </div>

                    <h1 class="max-w-3xl text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('downline_products.title') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('downline_products.description') }}
                    </p>
                </div>

                <div class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-sm">
                        {{ __('downline_products.total_estimated_commission') }}
                    </div>

                    <div class="mt-2 break-words text-3xl font-black leading-none text-[#003b7a] sm:text-5xl">
                        {{ $currency }} {{ number_format((float) ($summary['total_estimated_commission'] ?? 0), 2) }}
                    </div>

                    <p class="mt-3 text-xs font-bold text-[#003b7a]/70 sm:text-sm">
                        {{ __('downline_products.estimated_note') }}
                    </p>

                    <div class="mt-4 rounded-2xl bg-[#fff8c7] p-3">
                        <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                            {{ __('downline_products.claimed_commission_estimate') }}
                        </div>

                        <div class="mt-1 text-xl font-black text-[#e4003a]">
                            {{ $currency }} {{ number_format((float) ($summary['total_claimed_commission_estimate'] ?? 0), 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid grid-cols-3 gap-2 sm:gap-4">
            @foreach ($summaryCards as $card)
                <div class="rounded-[1rem] p-3 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-5 {{ $card['class'] }}">
                    <div class="line-clamp-2 text-[8px] font-black uppercase leading-tight opacity-70 sm:text-xs">
                        {{ $card['label'] }}
                    </div>

                    <div class="mt-2 break-words text-base font-black leading-tight sm:mt-3 sm:text-3xl">
                        {{ $card['value'] }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="-mx-3 mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-3 pb-2 sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0">
            @foreach ($levelCards as $card)
                <div class="w-[62vw] shrink-0 snap-start rounded-[1.35rem] bg-gradient-to-br {{ $card['class'] }} p-4 text-white shadow-xl sm:w-auto sm:rounded-[2rem] sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="text-4xl font-black leading-none sm:text-5xl">
                            {{ $card['label'] }}
                        </div>

                        <div class="rounded-full bg-white/20 px-3 py-1.5 text-[10px] font-black sm:text-xs">
                            {{ number_format((float) $card['rate'], 2) }}%
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3">
                        <div class="rounded-2xl bg-white/20 p-3">
                            <div class="text-[9px] font-black uppercase text-white/60">
                                {{ __('downline_products.users') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ number_format((int) $card['users']) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/20 p-3">
                            <div class="text-[9px] font-black uppercase text-white/60">
                                {{ __('downline_products.products') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ number_format((int) $card['products']) }}
                            </div>
                        </div>

                        <div class="col-span-2 rounded-2xl bg-white/20 p-3">
                            <div class="text-[9px] font-black uppercase text-white/60">
                                {{ __('downline_products.estimated_full_commission') }}
                            </div>

                            <div class="mt-1 break-words text-xl font-black">
                                {{ $currency }} {{ number_format((float) $card['commission'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mt-6 sm:rounded-[2rem] sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('downline_products.downline_users') }}
                    </h2>

                    <p class="mt-1 text-xs font-bold text-[#003b7a]/60 sm:text-sm">
                        {{ __('downline_products.downline_users_desc') }}
                    </p>
                </div>
            </div>

            @if ($groups->isNotEmpty())
                <div class="mt-4 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10">
                    <div class="hidden bg-[#fff8c7] px-4 py-3 text-xs font-black uppercase text-[#003b7a]/70 lg:grid lg:grid-cols-[1.35fr_0.7fr_0.75fr_0.95fr_0.95fr_145px] lg:gap-3">
                        <div>{{ __('downline_products.member') }}</div>
                        <div>{{ __('downline_products.level') }}</div>
                        <div class="text-right">{{ __('downline_products.products') }}</div>
                        <div class="text-right">{{ __('downline_products.claimed_commission_estimate') }}</div>
                        <div class="text-right">{{ __('downline_products.estimated_full_commission') }}</div>
                        <div class="text-right">{{ __('downline_products.action') }}</div>
                    </div>

                    <div class="divide-y divide-[#003b7a]/10 bg-white">
                        @foreach ($groups as $group)
                            <div class="p-3 transition hover:bg-[#fff8c7]/45 sm:p-4 lg:grid lg:grid-cols-[1.35fr_0.7fr_0.75fr_0.95fr_0.95fr_145px] lg:items-center lg:gap-3">
                                <div class="flex items-center justify-between gap-3 lg:block">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#003b7a] text-xs font-black uppercase text-white lg:hidden">
                                                {{ $group['level_label'] }}
                                            </span>

                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-black text-[#003b7a] sm:text-base">
                                                    {{ $group['username'] }}
                                                </div>

                                                <div class="truncate text-[11px] font-bold text-[#003b7a]/55 sm:text-xs">
                                                    {{ $group['name'] }}
                                                </div>
                                            </div>
                                        </div>

                                        @if ($group['phone'] && $group['phone'] !== '-')
                                            <div class="mt-1 truncate pl-10 text-[10px] font-bold text-[#003b7a]/40 lg:pl-0">
                                                {{ $group['phone'] }}
                                            </div>
                                        @endif
                                    </div>

                                    <button
                                        type="button"
                                        class="shrink-0 rounded-full bg-[#003b7a] px-3 py-2 text-[10px] font-black uppercase text-white shadow-sm lg:hidden"
                                        data-downline-detail
                                        data-products-id="downline-products-{{ $group['user_id'] }}"
                                        data-username="{{ $group['username'] }}"
                                        data-name="{{ $group['name'] }}"
                                        data-level="{{ $group['level_label'] }}"
                                        data-rate="{{ number_format((float) $group['rate'], 2) }}%"
                                    >
                                        {{ __('downline_products.view_products') }}
                                    </button>
                                </div>

                                <div class="mt-3 hidden lg:mt-0 lg:block">
                                    <span class="inline-flex rounded-full bg-[#003b7a] px-3 py-1 text-xs font-black uppercase text-white">
                                        {{ $group['level_label'] }}
                                    </span>

                                    <span class="ml-1 inline-flex rounded-full bg-[#fff8c7] px-3 py-1 text-xs font-black uppercase text-[#003b7a]">
                                        {{ number_format((float) $group['rate'], 2) }}%
                                    </span>
                                </div>

                                <div class="mt-3 grid grid-cols-3 gap-2 lg:mt-0 lg:block lg:text-right">
                                    <div class="rounded-xl bg-[#fff8c7] p-2 lg:bg-transparent lg:p-0">
                                        <div class="text-[9px] font-black uppercase text-[#003b7a]/45 lg:hidden">
                                            {{ __('downline_products.products') }}
                                        </div>

                                        <div class="mt-0.5 text-sm font-black text-[#003b7a] lg:text-base">
                                            {{ number_format((int) $group['product_count']) }}
                                        </div>
                                    </div>

                                    <div class="rounded-xl bg-[#fff8c7] p-2 lg:hidden">
                                        <div class="text-[9px] font-black uppercase text-[#003b7a]/45">
                                            {{ __('downline_products.total_sales') }}
                                        </div>

                                        <div class="mt-0.5 text-xs font-black text-[#003b7a]">
                                            {{ $currency }} {{ number_format((float) $group['total_sales'], 2) }}
                                        </div>
                                    </div>

                                    <div class="rounded-xl bg-[#fff8c7] p-2 lg:hidden">
                                        <div class="text-[9px] font-black uppercase text-[#003b7a]/45">
                                            {{ __('downline_products.estimated_full_commission') }}
                                        </div>

                                        <div class="mt-0.5 text-xs font-black text-[#e4003a]">
                                            {{ $currency }} {{ number_format((float) $group['total_estimated_commission'], 2) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="hidden text-right font-black text-[#e4003a] lg:block">
                                    {{ $currency }} {{ number_format((float) $group['total_claimed_commission_estimate'], 2) }}
                                </div>

                                <div class="hidden text-right font-black text-[#e4003a] lg:block">
                                    {{ $currency }} {{ number_format((float) $group['total_estimated_commission'], 2) }}
                                </div>

                                <div class="hidden text-right lg:block">
                                    <button
                                        type="button"
                                        class="rounded-full bg-[#003b7a] px-4 py-2 text-xs font-black uppercase text-white shadow-sm transition hover:bg-[#002b59]"
                                        data-downline-detail
                                        data-products-id="downline-products-{{ $group['user_id'] }}"
                                        data-username="{{ $group['username'] }}"
                                        data-name="{{ $group['name'] }}"
                                        data-level="{{ $group['level_label'] }}"
                                        data-rate="{{ number_format((float) $group['rate'], 2) }}%"
                                    >
                                        {{ __('downline_products.view_products') }}
                                    </button>
                                </div>

                                <script type="application/json" id="downline-products-{{ $group['user_id'] }}">
                                    {!! json_encode($group['products'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                                </script>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-5">
                    {{ $groups->links() }}
                </div>
            @else
                <div class="mt-4 rounded-[1.25rem] bg-[#fff8c7] p-6 text-center">
                    <div class="text-xl font-black text-[#003b7a]">
                        {{ __('downline_products.no_records') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('downline_products.no_records_desc') }}
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        .downline-modal-popup {
            width: min(960px, calc(100vw - 22px)) !important;
            padding: 0 !important;
            border-radius: 24px !important;
            overflow: hidden !important;
            background: #f8fafc !important;
        }

        .downline-modal-html {
            margin: 0 !important;
            padding: 0 !important;
        }

        .downline-modal-title {
            padding: 0 !important;
            margin: 0 !important;
        }

        .downline-modal-actions {
            margin: 0 !important;
            padding: 14px 18px 18px !important;
            border-top: 1px solid rgba(0, 59, 122, .08) !important;
            background: white !important;
        }

        .downline-modal-confirm {
            border: 0 !important;
            border-radius: 14px !important;
            background: #003b7a !important;
            color: #ffffff !important;
            padding: 10px 26px !important;
            font-weight: 900 !important;
            text-transform: uppercase !important;
            font-size: 12px !important;
            box-shadow: none !important;
        }

        .downline-modal-confirm:hover {
            background: #002b59 !important;
            color: #ffffff !important;
        }

        @media (max-width: 640px) {
            .downline-modal-popup {
                width: calc(100vw - 14px) !important;
                border-radius: 18px !important;
            }

            .downline-modal-actions {
                padding: 12px 12px 14px !important;
            }

            .downline-modal-confirm {
                width: 100% !important;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const currency = @json($currency);

            const labels = {
                level: @json(__('downline_products.level')),
                price: @json(__('downline_products.price')),
                products: @json(__('downline_products.products')),
                totalSales: @json(__('downline_products.total_sales')),
                incomePerClaim: @json(__('downline_products.income_per_claim')),
                progress: @json(__('downline_products.progress')),
                claimInterval: @json(__('downline_products.claim_interval')),
                commissionPerClaim: @json(__('downline_products.commission_per_claim')),
                estimatedFullCommission: @json(__('downline_products.estimated_full_commission')),
                claimedCommissionEstimate: @json(__('downline_products.claimed_commission_estimate')),
                noRecords: @json(__('downline_products.no_records')),
                close: @json(__('products.close')),
                hours: @json(__('products.hours')),
            };

            const money = (value) => {
                return `${currency} ${Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                })}`;
            };

            const number = (value) => {
                return Number(value || 0).toLocaleString();
            };

            const escapeHtml = (value) => {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            };

            const readProducts = (scriptId) => {
                const script = document.getElementById(scriptId);

                if (! script) {
                    return [];
                }

                try {
                    const parsed = JSON.parse(script.textContent || '[]');

                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            };

            const productRow = (product, index) => {
                const productName = escapeHtml(product.product_name || '-');
                const productCode = escapeHtml(product.product_code || '-');
                const status = escapeHtml(product.status || '-').toUpperCase();
                const purchasedAt = escapeHtml(product.purchased_at_label || '-');

                return `
                    <div style="
                        border-radius:16px;
                        background:white;
                        border:1px solid rgba(0,59,122,.10);
                        overflow:hidden;
                    ">
                        <div style="
                            display:grid;
                            grid-template-columns:minmax(0, 1.35fr) minmax(90px, .7fr) minmax(90px, .7fr) minmax(105px, .8fr);
                            gap:10px;
                            align-items:center;
                            padding:13px 14px;
                        " class="dl-product-row">
                            <div style="min-width:0;">
                                <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                                    <span style="
                                        flex-shrink:0;
                                        display:grid;
                                        width:24px;
                                        height:24px;
                                        place-items:center;
                                        border-radius:999px;
                                        background:#003b7a;
                                        color:white;
                                        font-size:10px;
                                        font-weight:900;
                                    ">
                                        ${index + 1}
                                    </span>

                                    <div style="min-width:0;">
                                        <div style="
                                            color:#003b7a;
                                            font-size:14px;
                                            font-weight:900;
                                            line-height:1.15;
                                            white-space:nowrap;
                                            overflow:hidden;
                                            text-overflow:ellipsis;
                                        ">
                                            ${productName}
                                        </div>

                                        <div style="
                                            margin-top:3px;
                                            color:rgba(0,59,122,.52);
                                            font-size:10px;
                                            font-weight:800;
                                            white-space:nowrap;
                                            overflow:hidden;
                                            text-overflow:ellipsis;
                                        ">
                                            ${productCode} · ${purchasedAt}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div style="text-align:right;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.45);text-transform:uppercase;">
                                    ${escapeHtml(labels.incomePerClaim)}
                                </div>
                                <div style="margin-top:3px;font-size:13px;font-weight:900;color:#003b7a;">
                                    ${money(product.income_per_claim)}
                                </div>
                            </div>

                            <div style="text-align:right;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.45);text-transform:uppercase;">
                                    ${escapeHtml(labels.progress)}
                                </div>
                                <div style="margin-top:3px;font-size:13px;font-weight:900;color:#003b7a;">
                                    ${number(product.claimed_count)} / ${number(product.total_claims)}
                                </div>
                            </div>

                            <div style="text-align:right;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.45);text-transform:uppercase;">
                                    ${escapeHtml(labels.estimatedFullCommission)}
                                </div>
                                <div style="margin-top:3px;font-size:13px;font-weight:900;color:#e4003a;">
                                    ${money(product.earning)}
                                </div>
                            </div>
                        </div>

                        <div style="
                            display:grid;
                            grid-template-columns:repeat(4, minmax(0, 1fr));
                            gap:1px;
                            background:rgba(0,59,122,.08);
                            border-top:1px solid rgba(0,59,122,.08);
                        " class="dl-product-detail-grid">
                            <div style="background:#fff8c7;padding:10px;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.5);text-transform:uppercase;">
                                    ${escapeHtml(labels.price)}
                                </div>
                                <div style="margin-top:4px;font-size:12px;font-weight:900;color:#003b7a;">
                                    ${money(product.price)}
                                </div>
                            </div>

                            <div style="background:#fff8c7;padding:10px;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.5);text-transform:uppercase;">
                                    ${escapeHtml(labels.claimInterval)}
                                </div>
                                <div style="margin-top:4px;font-size:12px;font-weight:900;color:#003b7a;">
                                    ${number(product.claim_interval_hours)} ${escapeHtml(labels.hours)}
                                </div>
                            </div>

                            <div style="background:#fff8c7;padding:10px;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.5);text-transform:uppercase;">
                                    ${escapeHtml(labels.commissionPerClaim)}
                                </div>
                                <div style="margin-top:4px;font-size:12px;font-weight:900;color:#e4003a;">
                                    ${money(product.commission_per_claim)}
                                </div>
                            </div>

                            <div style="background:#fff8c7;padding:10px;">
                                <div style="font-size:9px;font-weight:900;color:rgba(0,59,122,.5);text-transform:uppercase;">
                                    ${escapeHtml(labels.claimedCommissionEstimate)}
                                </div>
                                <div style="margin-top:4px;font-size:12px;font-weight:900;color:#e4003a;">
                                    ${money(product.claimed_commission_estimate)}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            };

            const modalHtml = ({ username, name, level, rate, products }) => {
                const totalProducts = products.length;
                const totalSales = products.reduce((total, product) => total + Number(product.price || 0), 0);
                const totalClaimedCommission = products.reduce((total, product) => total + Number(product.claimed_commission_estimate || 0), 0);
                const totalEstimatedCommission = products.reduce((total, product) => total + Number(product.earning || 0), 0);

                const rows = products.length > 0
                    ? products.map((product, index) => productRow(product, index)).join('')
                    : `
                        <div style="border-radius:16px;background:#fff8c7;padding:24px;text-align:center;color:#003b7a;font-weight:900;">
                            ${escapeHtml(labels.noRecords)}
                        </div>
                    `;

                return `
                    <style>
                        @media (max-width: 640px) {
                            .dl-modal-head {
                                padding: 14px !important;
                            }

                            .dl-modal-user-card {
                                border-radius: 18px !important;
                                padding: 14px !important;
                            }

                            .dl-modal-summary {
                                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                            }

                            .dl-modal-body {
                                padding: 10px !important;
                                max-height: 64vh !important;
                            }

                            .dl-product-row {
                                grid-template-columns: 1fr !important;
                                gap: 8px !important;
                                padding: 12px !important;
                            }

                            .dl-product-row > div {
                                text-align: left !important;
                            }

                            .dl-product-detail-grid {
                                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                            }
                        }
                    </style>

                    <div style="text-align:left;">
                        <div class="dl-modal-head" style="
                            position:sticky;
                            top:0;
                            z-index:2;
                            background:white;
                            padding:18px;
                            border-bottom:1px solid rgba(0,59,122,.08);
                        ">
                            <div class="dl-modal-user-card" style="
                                border-radius:22px;
                                background:#003b7a;
                                color:white;
                                padding:18px;
                            ">
                                <div style="
                                    display:flex;
                                    align-items:flex-start;
                                    justify-content:space-between;
                                    gap:12px;
                                ">
                                    <div style="min-width:0;">
                                        <div style="
                                            font-size:22px;
                                            font-weight:900;
                                            line-height:1.1;
                                            word-break:break-word;
                                        ">
                                            ${escapeHtml(username)}
                                        </div>

                                        <div style="
                                            margin-top:5px;
                                            font-size:13px;
                                            font-weight:800;
                                            color:rgba(255,255,255,.68);
                                            word-break:break-word;
                                        ">
                                            ${escapeHtml(name)}
                                        </div>
                                    </div>

                                    <div style="
                                        flex-shrink:0;
                                        display:flex;
                                        gap:6px;
                                        flex-wrap:wrap;
                                        justify-content:flex-end;
                                    ">
                                        <span style="
                                            border-radius:999px;
                                            background:#ffd900;
                                            color:#003b7a;
                                            padding:7px 11px;
                                            font-size:11px;
                                            font-weight:900;
                                            white-space:nowrap;
                                        ">
                                            ${escapeHtml(labels.level)} ${escapeHtml(level)}
                                        </span>

                                        <span style="
                                            border-radius:999px;
                                            background:white;
                                            color:#003b7a;
                                            padding:7px 11px;
                                            font-size:11px;
                                            font-weight:900;
                                            white-space:nowrap;
                                        ">
                                            ${escapeHtml(rate)}
                                        </span>
                                    </div>
                                </div>

                                <div class="dl-modal-summary" style="
                                    display:grid;
                                    grid-template-columns:repeat(4, minmax(0, 1fr));
                                    gap:8px;
                                    margin-top:14px;
                                ">
                                    <div style="border-radius:14px;background:rgba(255,255,255,.12);padding:11px;">
                                        <div style="font-size:10px;font-weight:900;color:rgba(255,255,255,.6);text-transform:uppercase;">
                                            ${escapeHtml(labels.products)}
                                        </div>
                                        <div style="margin-top:4px;font-size:18px;font-weight:900;">
                                            ${number(totalProducts)}
                                        </div>
                                    </div>

                                    <div style="border-radius:14px;background:rgba(255,255,255,.12);padding:11px;">
                                        <div style="font-size:10px;font-weight:900;color:rgba(255,255,255,.6);text-transform:uppercase;">
                                            ${escapeHtml(labels.totalSales)}
                                        </div>
                                        <div style="margin-top:4px;font-size:16px;font-weight:900;">
                                            ${money(totalSales)}
                                        </div>
                                    </div>

                                    <div style="border-radius:14px;background:rgba(255,255,255,.12);padding:11px;">
                                        <div style="font-size:10px;font-weight:900;color:rgba(255,255,255,.6);text-transform:uppercase;">
                                            ${escapeHtml(labels.claimedCommissionEstimate)}
                                        </div>
                                        <div style="margin-top:4px;font-size:16px;font-weight:900;color:#ffd900;">
                                            ${money(totalClaimedCommission)}
                                        </div>
                                    </div>

                                    <div style="border-radius:14px;background:rgba(255,255,255,.12);padding:11px;">
                                        <div style="font-size:10px;font-weight:900;color:rgba(255,255,255,.6);text-transform:uppercase;">
                                            ${escapeHtml(labels.estimatedFullCommission)}
                                        </div>
                                        <div style="margin-top:4px;font-size:16px;font-weight:900;color:#ffd900;">
                                            ${money(totalEstimatedCommission)}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dl-modal-body" style="
                            max-height:62vh;
                            overflow-y:auto;
                            padding:14px 18px 18px;
                            display:grid;
                            gap:10px;
                            background:#f8fafc;
                        ">
                            ${rows}
                        </div>
                    </div>
                `;
            };

            document.querySelectorAll('[data-downline-detail]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const products = readProducts(button.dataset.productsId || '');

                    await Swal.fire({
                        title: '',
                        html: modalHtml({
                            username: button.dataset.username || '-',
                            name: button.dataset.name || '-',
                            level: button.dataset.level || '-',
                            rate: button.dataset.rate || '-',
                            products,
                        }),
                        customClass: {
                            popup: 'downline-modal-popup',
                            htmlContainer: 'downline-modal-html',
                            title: 'downline-modal-title',
                            actions: 'downline-modal-actions',
                            confirmButton: 'downline-modal-confirm',
                        },
                        buttonsStyling: false,
                        showConfirmButton: true,
                        confirmButtonText: labels.close,
                        width: 960,
                    });
                });
            });
        });
    </script>
@endpush