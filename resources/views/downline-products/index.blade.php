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
                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($groups as $group)
                        <article class="rounded-[1.25rem] bg-[#fff8c7] p-4 shadow-sm ring-1 ring-[#003b7a]/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-[#003b7a] px-2.5 py-1 text-[10px] font-black uppercase text-white">
                                            {{ $group['level_label'] }}
                                        </span>

                                        <span class="truncate text-sm font-black text-[#003b7a]">
                                            {{ $group['username'] }}
                                        </span>
                                    </div>

                                    <div class="mt-1 truncate text-xs font-bold text-[#003b7a]/60">
                                        {{ $group['name'] }}
                                    </div>

                                    @if ($group['phone'] && $group['phone'] !== '-')
                                        <div class="mt-1 truncate text-[11px] font-bold text-[#003b7a]/45">
                                            {{ $group['phone'] }}
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 rounded-full bg-white px-3 py-1 text-[10px] font-black uppercase text-[#003b7a]">
                                    {{ number_format((float) $group['rate'], 2) }}%
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.products') }}
                                    </div>

                                    <div class="mt-1 text-lg font-black text-[#003b7a]">
                                        {{ number_format((int) $group['product_count']) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.total_sales') }}
                                    </div>

                                    <div class="mt-1 text-sm font-black text-[#003b7a]">
                                        {{ $currency }} {{ number_format((float) $group['total_sales'], 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.claimed_commission_estimate') }}
                                    </div>

                                    <div class="mt-1 text-sm font-black text-[#e4003a]">
                                        {{ $currency }} {{ number_format((float) $group['total_claimed_commission_estimate'], 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.estimated_full_commission') }}
                                    </div>

                                    <div class="mt-1 text-sm font-black text-[#e4003a]">
                                        {{ $currency }} {{ number_format((float) $group['total_estimated_commission'], 2) }}
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="mt-4 w-full rounded-2xl bg-[#003b7a] px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#002b59]"
                                data-downline-detail
                                data-username="{{ $group['username'] }}"
                                data-name="{{ $group['name'] }}"
                                data-level="{{ $group['level_label'] }}"
                                data-rate="{{ number_format((float) $group['rate'], 2) }}%"
                                data-products='@json($group['products'])'
                            >
                                {{ __('downline_products.view_products') }}
                            </button>
                        </article>
                    @endforeach
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
            width: min(920px, calc(100vw - 24px)) !important;
            padding: 0 !important;
            border-radius: 28px !important;
            overflow: hidden !important;
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
            padding: 16px 18px 20px !important;
            border-top: 1px solid rgba(0, 59, 122, .08) !important;
        }

        .downline-modal-confirm {
            border: 0 !important;
            border-radius: 14px !important;
            background: #003b7a !important;
            color: #ffffff !important;
            padding: 10px 24px !important;
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
                width: calc(100vw - 18px) !important;
                border-radius: 20px !important;
            }

            .downline-modal-actions {
                padding: 12px 14px 16px !important;
            }

            .downline-modal-confirm {
                width: 100% !important;
                color: #ffffff !important;
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

            const metric = (label, value, accent = false) => {
                return `
                    <div style="border-radius:14px;background:white;padding:12px;min-width:0;">
                        <div style="font-size:10px;font-weight:900;color:rgba(0,59,122,.52);text-transform:uppercase;line-height:1.15;">
                            ${escapeHtml(label)}
                        </div>

                        <div style="margin-top:6px;font-size:14px;font-weight:900;line-height:1.15;color:${accent ? '#e4003a' : '#003b7a'};word-break:break-word;">
                            ${value}
                        </div>
                    </div>
                `;
            };

            const productCard = (product, index) => {
                const productName = escapeHtml(product.product_name || '-');
                const productCode = escapeHtml(product.product_code || '-');
                const status = escapeHtml(product.status || '-').toUpperCase();
                const purchasedAt = escapeHtml(product.purchased_at_label || '-');

                return `
                    <div style="border-radius:20px;background:#fff8c7;border:1px solid rgba(0,59,122,.12);padding:14px;">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                            <div style="min-width:0;">
                                <div style="display:inline-flex;align-items:center;border-radius:999px;background:#003b7a;color:white;padding:4px 9px;font-size:10px;font-weight:900;margin-bottom:8px;">
                                    #${index + 1}
                                </div>

                                <div style="color:#003b7a;font-size:17px;font-weight:900;line-height:1.15;word-break:break-word;">
                                    ${productName}
                                </div>

                                <div style="margin-top:4px;color:rgba(0,59,122,.58);font-size:11px;font-weight:800;line-height:1.35;">
                                    ${productCode} · ${purchasedAt}
                                </div>
                            </div>

                            <div style="flex-shrink:0;border-radius:999px;background:#003b7a;color:white;padding:6px 10px;font-size:10px;font-weight:900;text-transform:uppercase;white-space:nowrap;">
                                ${status}
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:8px;margin-top:12px;">
                            ${metric(labels.price, money(product.price))}
                            ${metric(labels.incomePerClaim, money(product.income_per_claim))}
                            ${metric(labels.progress, `${number(product.claimed_count)} / ${number(product.total_claims)}`)}
                            ${metric(labels.claimInterval, `${number(product.claim_interval_hours)} ${escapeHtml(labels.hours)}`)}
                            ${metric(labels.commissionPerClaim, money(product.commission_per_claim), true)}
                            ${metric(labels.estimatedFullCommission, money(product.earning), true)}
                        </div>

                        <div style="margin-top:8px;border-radius:14px;background:rgba(0,59,122,.06);padding:10px 12px;">
                            <div style="font-size:10px;font-weight:900;text-transform:uppercase;color:rgba(0,59,122,.52);">
                                ${escapeHtml(labels.claimedCommissionEstimate)}
                            </div>

                            <div style="margin-top:4px;color:#e4003a;font-size:15px;font-weight:900;">
                                ${money(product.claimed_commission_estimate)}
                            </div>
                        </div>
                    </div>
                `;
            };

            document.querySelectorAll('[data-downline-detail]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const username = escapeHtml(button.dataset.username || '-');
                    const name = escapeHtml(button.dataset.name || '-');
                    const level = escapeHtml(button.dataset.level || '-');
                    const rate = escapeHtml(button.dataset.rate || '-');

                    let products = [];

                    try {
                        products = JSON.parse(button.dataset.products || '[]');
                    } catch (error) {
                        products = [];
                    }

                    const totalProducts = products.length;
                    const totalSales = products.reduce((total, product) => total + Number(product.price || 0), 0);
                    const totalClaimedCommission = products.reduce((total, product) => total + Number(product.claimed_commission_estimate || 0), 0);
                    const totalEstimatedCommission = products.reduce((total, product) => total + Number(product.earning || 0), 0);

                    const productRows = products.length > 0
                        ? products.map((product, index) => productCard(product, index)).join('')
                        : `
                            <div style="border-radius:18px;background:#fff8c7;padding:24px;text-align:center;color:#003b7a;font-weight:900;">
                                ${escapeHtml(labels.noRecords)}
                            </div>
                        `;

                    await Swal.fire({
                        title: '',
                        html: `
                            <div style="text-align:left;">
                                <div style="position:sticky;top:0;z-index:2;background:white;padding:18px;border-bottom:1px solid rgba(0,59,122,.08);">
                                    <div style="border-radius:22px;background:#003b7a;color:white;padding:18px;">
                                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                            <div style="min-width:0;">
                                                <div style="font-size:22px;font-weight:900;line-height:1.1;word-break:break-word;">
                                                    ${username}
                                                </div>

                                                <div style="margin-top:5px;font-size:13px;font-weight:800;color:rgba(255,255,255,.68);word-break:break-word;">
                                                    ${name}
                                                </div>
                                            </div>

                                            <div style="flex-shrink:0;display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                                                <span style="border-radius:999px;background:#ffd900;color:#003b7a;padding:7px 11px;font-size:11px;font-weight:900;white-space:nowrap;">
                                                    ${escapeHtml(labels.level)} ${level}
                                                </span>

                                                <span style="border-radius:999px;background:white;color:#003b7a;padding:7px 11px;font-size:11px;font-weight:900;white-space:nowrap;">
                                                    ${rate}
                                                </span>
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:8px;margin-top:14px;">
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

                                <div style="max-height:62vh;overflow-y:auto;padding:14px 18px 18px;display:grid;gap:12px;background:#f8fafc;">
                                    ${productRows}
                                </div>
                            </div>
                        `,
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
                        width: 920,
                    });
                });
            });
        });
    </script>
@endpush