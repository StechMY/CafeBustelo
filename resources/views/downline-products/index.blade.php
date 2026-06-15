@extends('layouts.app')

@section('title', __('downline_products.title'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');

        $summaryCards = [
            [
                'label' => __('downline_products.total_products'),
                'value' => number_format($summary['total_products']),
                'class' => 'bg-[#003b7a] text-white',
            ],
            [
                'label' => __('downline_products.total_sales'),
                'value' => $currency . ' ' . number_format((float) $summary['total_sales'], 2),
                'class' => 'bg-[#e4003a] text-white',
            ],
            [
                'label' => __('downline_products.total_estimated_commission'),
                'value' => $currency . ' ' . number_format((float) $summary['total_estimated_commission'], 2),
                'class' => 'bg-white text-[#003b7a]',
            ],
        ];

        $levelCards = [
            [
                'label' => 'B',
                'products' => $summary['b_products'],
                'commission' => $summary['b_estimated_commission'],
                'commission_per_claim' => $summary['b_commission_per_claim'] ?? 0,
                'rate' => $commissionRates->get(1)?->rate_percent ?? 0,
                'class' => 'from-[#ff3fac] to-[#e4003a]',
            ],
            [
                'label' => 'C',
                'products' => $summary['c_products'],
                'commission' => $summary['c_estimated_commission'],
                'commission_per_claim' => $summary['c_commission_per_claim'] ?? 0,
                'rate' => $commissionRates->get(2)?->rate_percent ?? 0,
                'class' => 'from-[#22d3c5] to-[#009b8f]',
            ],
            [
                'label' => 'D',
                'products' => $summary['d_products'],
                'commission' => $summary['d_estimated_commission'],
                'commission_per_claim' => $summary['d_commission_per_claim'] ?? 0,
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
                        {{ $currency }} {{ number_format((float) $summary['total_estimated_commission'], 2) }}
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
                                {{ __('downline_products.products') }}
                            </div>

                            <div class="mt-1 text-2xl font-black">
                                {{ number_format($card['products']) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/20 p-3">
                            <div class="text-[9px] font-black uppercase text-white/60">
                                {{ __('downline_products.commission_per_claim') }}
                            </div>

                            <div class="mt-1 text-lg font-black">
                                {{ $currency }} {{ number_format((float) $card['commission_per_claim'], 2) }}
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
                        {{ __('downline_products.product_details') }}
                    </h2>

                    <p class="mt-1 text-xs font-bold text-[#003b7a]/60 sm:text-sm">
                        {{ __('downline_products.product_details_desc') }}
                    </p>
                </div>
            </div>

            @if ($products->isNotEmpty())
                <div class="mt-4 divide-y divide-[#003b7a]/10 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10 xl:hidden">
                    @foreach ($products as $row)
                        @php
                            $userProduct = $row['user_product'];
                        @endphp

                        <div class="bg-[#fff8c7] p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-black text-[#003b7a]">
                                        {{ $userProduct->product?->name ?? data_get($userProduct->meta, 'product_name_snapshot', '-') }}
                                    </div>

                                    <div class="mt-1 text-[10px] font-bold text-[#003b7a]/60">
                                        {{ $userProduct->user?->username }} · {{ __('downline_products.level') }} {{ $row['level_label'] }}
                                    </div>

                                    <div class="mt-1 text-[10px] font-bold text-[#003b7a]/60">
                                        {{ $userProduct->purchased_at?->format('d M Y, h:i A') }}
                                    </div>
                                </div>

                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-black text-[#e4003a]">
                                        {{ $currency }} {{ number_format((float) $row['earning'], 2) }}
                                    </div>

                                    <div class="mt-0.5 text-[9px] font-bold text-[#003b7a]/50">
                                        {{ number_format((float) $row['rate'], 2) }}%
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-white p-2.5">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.income_per_claim') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#003b7a]">
                                        {{ $currency }} {{ number_format((float) $row['income_per_claim'], 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-2.5">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.commission_per_claim') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#e4003a]">
                                        {{ $currency }} {{ number_format((float) $row['commission_per_claim'], 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-2.5">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.progress') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#003b7a]">
                                        {{ number_format((int) $row['claimed_count']) }}/{{ number_format((int) $row['total_claims']) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-2.5">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('downline_products.claim_interval') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#003b7a]">
                                        {{ number_format((int) $row['claim_interval_hours']) }} {{ __('products.hours') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 hidden overflow-x-auto rounded-[1.25rem] border border-[#003b7a]/10 xl:block">
                    <table class="w-full min-w-[1200px] table-fixed divide-y divide-[#003b7a]/10 text-sm">
                        <thead class="bg-[#fff8c7]">
                            <tr>
                                <th class="w-[155px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.member') }}
                                </th>

                                <th class="w-[75px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.level') }}
                                </th>

                                <th class="w-[190px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.product') }}
                                </th>

                                <th class="w-[120px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.price') }}
                                </th>

                                <th class="w-[135px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.income_per_claim') }}
                                </th>

                                <th class="w-[105px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.total_claims') }}
                                </th>

                                <th class="w-[105px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.rate') }}
                                </th>

                                <th class="w-[145px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.commission_per_claim') }}
                                </th>

                                <th class="w-[160px] px-3 py-3 text-right text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.estimated_full_commission') }}
                                </th>

                                <th class="w-[150px] px-3 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('downline_products.purchased_at') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#003b7a]/10 bg-white">
                            @foreach ($products as $row)
                                @php
                                    $userProduct = $row['user_product'];
                                @endphp

                                <tr class="hover:bg-[#fff8c7]/40">
                                    <td class="px-3 py-3 align-top">
                                        <div class="font-black text-[#003b7a]">
                                            {{ $userProduct->user?->username ?? '-' }}
                                        </div>

                                        <div class="truncate text-xs font-bold text-[#003b7a]/55">
                                            {{ $userProduct->user?->name ?? '-' }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <span class="rounded-full bg-[#003b7a] px-3 py-1 text-xs font-black uppercase text-white">
                                            {{ $row['level_label'] }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-3 align-top">
                                        <div class="line-clamp-2 font-black leading-tight text-[#003b7a]">
                                            {{ $userProduct->product?->name ?? data_get($userProduct->meta, 'product_name_snapshot', '-') }}
                                        </div>

                                        <div class="mt-1 text-xs font-bold text-[#003b7a]/55">
                                            {{ $userProduct->product?->code ?? data_get($userProduct->meta, 'product_code_snapshot', '-') }}
                                        </div>

                                        <div class="mt-1 text-[10px] font-bold text-[#003b7a]/45">
                                            {{ __('downline_products.claimed') }}:
                                            {{ number_format((int) $row['claimed_count']) }}/{{ number_format((int) $row['total_claims']) }}
                                            ·
                                            {{ number_format((int) $row['claim_interval_hours']) }}{{ __('products.hours_short') }}
                                        </div>
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black text-[#003b7a]">
                                        {{ $currency }} {{ number_format((float) $row['price'], 2) }}
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black text-[#003b7a]">
                                        {{ $currency }} {{ number_format((float) $row['income_per_claim'], 2) }}
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black text-[#003b7a]">
                                        {{ number_format((int) $row['total_claims']) }}
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black text-[#003b7a]">
                                        {{ number_format((float) $row['rate'], 2) }}%
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black text-[#e4003a]">
                                        {{ $currency }} {{ number_format((float) $row['commission_per_claim'], 2) }}
                                    </td>

                                    <td class="px-3 py-3 text-right align-top font-black text-[#e4003a]">
                                        {{ $currency }} {{ number_format((float) $row['earning'], 2) }}
                                    </td>

                                    <td class="px-3 py-3 align-top font-bold text-[#003b7a]/65">
                                        {{ $userProduct->purchased_at?->format('d M Y') }}<br>
                                        <span class="text-xs">
                                            {{ $userProduct->purchased_at?->format('h:i A') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $products->links() }}
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