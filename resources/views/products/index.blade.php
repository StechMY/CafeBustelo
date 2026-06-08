@extends('layouts.app')

@section('title', __('products.products'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');

        $dailyIncomeClaimRouteName = \Illuminate\Support\Facades\Route::has('daily-income.claim')
            ? 'daily-income.claim'
            : (\Illuminate\Support\Facades\Route::has('user-products.claim')
                ? 'user-products.claim'
                : null);
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.45fr_0.85fr] lg:items-center">
                <div>
                    <div
                         class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('products.product_center') }}
                    </div>

                    <h1
                        class="max-w-3xl text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('products.hero_title') }}
                    </h1>

                    <p
                       class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('products.hero_description') }}
                    </p>
                </div>

                <div
                     class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="flex items-center justify-between gap-3 lg:block">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-sm">
                                {{ __('dashboard.recharge_wallet') }}
                            </div>

                            <div
                                 class="mt-1 break-words text-2xl font-black leading-none text-[#003b7a] sm:mt-2 sm:text-5xl">
                                {{ $currency }} {{ number_format((float) $rechargeBalance, 2) }}
                            </div>

                            <p class="mt-2 hidden text-xs font-bold text-[#003b7a]/70 sm:mt-3 sm:block sm:text-sm">
                                {{ __('products.recharge_balance_description') }}
                            </p>
                        </div>

                        <div class="grid shrink-0 grid-cols-1 gap-2 sm:mt-4 sm:grid-cols-2 lg:flex lg:flex-wrap lg:gap-3">
                            <a
                               href="{{ route('recharge.index') }}"
                               class="rounded-full bg-[#003b7a] px-4 py-2 text-center text-[10px] font-black uppercase text-white shadow-lg hover:bg-[#002b59] sm:px-5 sm:py-3 sm:text-sm">
                                {{ __('dashboard.recharge') }}
                            </a>

                            <a
                               href="{{ route('dashboard') }}"
                               class="rounded-full bg-[#ffd900] px-4 py-2 text-center text-[10px] font-black uppercase text-[#003b7a] shadow-lg sm:px-5 sm:py-3 sm:text-sm">
                                {{ __('dashboard.dashboard') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        @if ($activeUserProducts->isNotEmpty())
            <div class="mb-5 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mb-8 sm:rounded-[2rem] sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('products.my_active_products') }}
                        </h2>

                        <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/60 sm:line-clamp-none sm:text-sm">
                            {{ __('products.my_active_products_description') }}
                        </p>
                    </div>

                    <div
                         class="shrink-0 rounded-full bg-[#e4003a] px-3 py-1.5 text-[10px] font-black uppercase text-white sm:text-xs">
                        {{ __('products.total_active_products', ['count' => $activeUserProducts->count()]) }}
                    </div>
                </div>

                <div
                     class="-mx-4 mt-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 [-webkit-overflow-scrolling:touch] sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-3">
                    @foreach ($activeUserProducts as $userProduct)
                        @php
                            $isCompleted =
                                (int) $userProduct->claimed_days >= (int) $userProduct->duration_days_snapshot;
                            $isClaimable =
                                !$isCompleted && $userProduct->next_claim_at && $userProduct->next_claim_at->lte(now());
                        @endphp

                        <div
                             class="w-[82vw] shrink-0 snap-start rounded-[1.35rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:w-auto sm:shrink sm:rounded-[1.5rem] sm:p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-black text-[#003b7a] sm:text-base">
                                        {{ $userProduct->product?->name ?? __('products.product') }}
                                    </div>

                                    <div class="mt-1 text-[10px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('products.purchased_at') }}:
                                        {{ $userProduct->purchased_at?->format('d M, h:i A') }}
                                    </div>
                                </div>

                                <span
                                      class="shrink-0 rounded-full bg-[#003b7a] px-2.5 py-1 text-[10px] font-black uppercase text-white">
                                    {{ __('status.' . $userProduct->status) }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-white p-2.5 sm:p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('dashboard.daily') }}
                                    </div>

                                    <div class="mt-1 text-base font-black text-[#e4003a] sm:text-lg">
                                        {{ $currency }}
                                        {{ number_format((float) $userProduct->daily_income_snapshot, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-2.5 sm:p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('products.progress') }}
                                    </div>

                                    <div class="mt-1 text-base font-black text-[#003b7a] sm:text-lg">
                                        {{ $userProduct->claimed_days }}/{{ $userProduct->duration_days_snapshot }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 rounded-2xl bg-white p-2.5 sm:p-3">
                                <div class="text-[10px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('dashboard.next_claim') }}
                                </div>

                                <div class="mt-1 text-xs font-black text-[#003b7a]">
                                    {{ $userProduct->next_claim_at?->format('d M Y, h:i A') ?? '-' }}
                                </div>
                            </div>

                            @if ($isCompleted)
                                <button
                                        type="button"
                                        disabled
                                        class="mt-3 w-full cursor-not-allowed rounded-2xl bg-gray-200 px-4 py-2.5 text-xs font-black uppercase text-gray-500">
                                    {{ __('products.completed') }}
                                </button>
                            @elseif ($isClaimable && $dailyIncomeClaimRouteName)
                                <form method="POST" action="{{ route($dailyIncomeClaimRouteName, $userProduct) }}"
                                      class="mt-3 js-claim-form">
                                    @csrf

                                    <button
                                            type="submit"
                                            class="w-full rounded-2xl bg-[#e4003a] px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#c90032]">
                                        {{ __('products.claim_now') }}
                                    </button>
                                </form>
                            @elseif ($isClaimable && !$dailyIncomeClaimRouteName)
                                <button
                                        type="button"
                                        disabled
                                        class="mt-3 w-full cursor-not-allowed rounded-2xl bg-red-100 px-4 py-2.5 text-xs font-black uppercase text-red-700">
                                    Claim route missing
                                </button>
                            @else
                                <button
                                        type="button"
                                        disabled
                                        class="mt-3 w-full cursor-not-allowed rounded-2xl bg-white px-4 py-2.5 text-xs font-black uppercase text-[#003b7a]/45">
                                    {{ __('products.not_ready_yet') }}
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mb-3 flex items-end justify-between gap-3 sm:mb-6">
            <div>
                <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-3xl">
                    {{ __('products.available_products') }}
                </h2>

                <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/60 sm:line-clamp-none sm:text-sm">
                    {{ __('products.available_products_description') }}
                </p>
            </div>
        </div>

        @if ($products->isNotEmpty())
            <div class="grid grid-cols-2 gap-2 sm:gap-5 md:grid-cols-3 xl:grid-cols-4">
                @foreach ($products as $product)
                    @php
                        $canAfford = (float) $rechargeBalance >= (float) $product->price;
                        $hasOwnedBefore = in_array($product->id, $ownedProductIdsForUpgradeBonus, true);
                        $totalIncome = (float) $product->daily_income * (int) $product->duration_days;
                        $profit = $totalIncome - (float) $product->price;
                        $roi = $product->price > 0 ? ($profit / (float) $product->price) * 100 : 0;
                        $purchasedCount = $userProductPurchaseCounts[$product->id] ?? 0;
                        $maxPurchase = max(1, (int) ($product->max_purchase_per_user ?: 2));
                        $limitReached = $purchasedCount >= $maxPurchase;
                        $canPurchase = $canAfford && !$limitReached;
                    @endphp

                    <article
                             class="relative overflow-hidden rounded-[1rem] bg-white shadow-lg ring-1 ring-black/5 sm:rounded-[1.75rem]">
                        <div class="absolute right-0 top-0 h-10 w-10 rounded-bl-[0.9rem] bg-[#ffd900] sm:h-20 sm:w-20">
                        </div>

                        <div class="relative z-10 p-2.5 sm:p-5">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div
                                         class="inline-flex rounded-full bg-[#003b7a] px-2 py-0.5 text-[8px] font-black uppercase text-white sm:px-3 sm:py-1 sm:text-xs">
                                        {{ $product->code }}
                                    </div>

                                    <h3
                                        class="mt-1 line-clamp-2 min-h-[1.65rem] text-[11px] font-black uppercase leading-none text-[#003b7a] sm:mt-2 sm:min-h-[2.6rem] sm:text-xl">
                                        {{ $product->name }}
                                    </h3>
                                </div>

                                @if ($hasOwnedBefore)
                                    <div
                                         class="shrink-0 rounded-full bg-gray-100 px-1.5 py-0.5 text-[8px] font-black uppercase text-gray-500 sm:px-2 sm:py-1 sm:text-[9px]">
                                        {{ __('products.owned_short') }}
                                    </div>
                                @endif
                            </div>

                            <div class="mt-2 overflow-hidden rounded-[0.85rem] bg-[#fff8c7] sm:mt-3 sm:rounded-[1.25rem]">
                                @if ($product->image_url)
                                    <img
                                         src="{{ $product->image_url }}"
                                         alt="{{ $product->name }}"
                                         class="aspect-[1.15/1] w-full object-cover sm:aspect-[4/3]"
                                         loading="lazy">
                                @else
                                    <div
                                         class="grid aspect-[1.15/1] w-full place-items-center bg-[#ffd900] sm:aspect-[4/3]">
                                        <div class="text-center">
                                            <img
                                                 src="{{ asset('logo.jpg') }}"
                                                 alt="{{ __('app.brand_name') }}"
                                                 class="mx-auto h-7 w-7 rounded-full object-cover shadow-sm ring-2 ring-[#ffd900] sm:h-11 sm:w-11 lg:h-12 lg:w-12">

                                            <div
                                                 class="mt-1 text-[7px] font-black uppercase text-[#003b7a]/70 sm:mt-2 sm:text-[10px]">
                                                {{ __('products.no_image') }}
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-2 rounded-[0.85rem] bg-[#ffd900] p-2 sm:mt-3 sm:rounded-[1rem] sm:p-3">
                                <div class="text-[7px] font-black uppercase text-[#003b7a]/65 sm:text-[9px]">
                                    {{ __('products.product_price') }}
                                </div>

                                <div class="mt-0.5 text-lg font-black leading-none text-[#003b7a] sm:text-2xl">
                                    {{ $currency }} {{ number_format((float) $product->price, 0) }}
                                </div>
                            </div>

                            <div class="mt-1.5 grid grid-cols-2 gap-1.5 sm:mt-2 sm:gap-2">
                                <div class="rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.daily_income') }}
                                    </div>

                                    <div class="mt-0.5 text-[11px] font-black text-[#e4003a] sm:mt-1 sm:text-sm">
                                        {{ $currency }} {{ number_format((float) $product->daily_income, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.duration') }}
                                    </div>

                                    <div class="mt-0.5 text-[11px] font-black text-[#003b7a] sm:mt-1 sm:text-sm">
                                        {{ $product->duration_days }} {{ __('products.days') }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-1 gap-1.5">
                                <button
                                        type="button"
                                        class="w-full rounded-lg border border-[#003b7a]/15 bg-white px-2 py-1.5 text-[9px] font-black text-[#003b7a] sm:rounded-xl sm:px-2.5 sm:py-2 sm:text-[11px]"
                                        data-product-detail
                                        data-name="{{ $product->name }}"
                                        data-code="{{ $product->code }}"
                                        data-price="{{ $currency }} {{ number_format((float) $product->price, 2) }}"
                                        data-daily="{{ $currency }} {{ number_format((float) $product->daily_income, 2) }}"
                                        data-days="{{ $product->duration_days }} {{ __('products.days') }}"
                                        data-total="{{ $currency }} {{ number_format($totalIncome, 2) }}"
                                        data-profit="{{ $currency }} {{ number_format($profit, 2) }}"
                                        data-roi="+{{ number_format($roi, 0) }}%"
                                        data-upgrade="{{ $currency }} {{ number_format((float) $product->upgrade_bonus, 2) }}"
                                        data-owned="{{ $hasOwnedBefore ? __('products.already_owned') : __('products.available') }}">
                                    {{ __('products.view_details') }}
                                </button>

                                <form
                                      method="POST"
                                      action="{{ route('products.purchase', $product) }}"
                                      class="js-purchase-form"
                                      data-can-afford="{{ $canAfford ? '1' : '0' }}"
                                      data-name="{{ $product->name }}"
                                      data-amount="{{ number_format((float) $product->price, 2) }}"
                                      data-balance="{{ number_format((float) $rechargeBalance, 2) }}">
                                    @csrf

                                    <button
                                            type="submit"
                                            @disabled(!$canPurchase)
                                            class="w-full rounded-lg px-2 py-2 text-[9px] font-black uppercase shadow-md transition sm:rounded-2xl sm:px-3 sm:py-3 sm:text-sm
    {{ $canPurchase
        ? 'bg-[#e4003a] text-white hover:-translate-y-0.5 hover:bg-[#c90032]'
        : 'cursor-not-allowed bg-gray-200 text-gray-500' }}">
                                        @if ($limitReached)
                                            {{ __('products.purchase_limit_reached') }}
                                        @elseif (!$canAfford)
                                            {{ __('products.insufficient_balance') }}
                                        @else
                                            {{ __('products.purchase_now') }}
                                        @endif
                                    </button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="rounded-[2rem] bg-white p-10 text-center shadow-xl ring-1 ring-black/5">
                <div class="text-3xl font-black text-[#003b7a]">
                    {{ __('products.no_products_available') }}
                </div>

                <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                    {{ __('products.no_products_available_description') }}
                </p>
            </div>
        @endif
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-claim-form').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const result = await Swal.fire({
                        icon: 'question',
                        title: @json(__('products.claim_now')),
                        text: @json(__('products.claim_confirm_note')),
                        showCancelButton: true,
                        confirmButtonText: @json(__('products.claim_now')),
                        cancelButtonText: @json(__('products.cancel')),
                        confirmButtonColor: '#e4003a',
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true,
                    });

                    if (result.isConfirmed) {
                        Swal.fire({
                            title: @json(__('products.processing')),
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            },
                        });

                        form.submit();
                    }
                });
            });

            document.querySelectorAll('.js-purchase-form').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const canAfford = form.dataset.canAfford === '1';
                    const name = form.dataset.name;
                    const amount = form.dataset.amount;
                    const balance = form.dataset.balance;

                    if (!canAfford) {
                        const result = await Swal.fire({
                            icon: 'warning',
                            title: @json(__('products.insufficient_balance')),
                            html: `
                                <div style="font-weight:700; color:#003b7a;">
                                    {{ __('products.current_balance') }}: {{ $currency }} ${balance}
                                </div>
                                <div style="margin-top:8px; font-size:14px; color:#6b7280;">
                                    {{ __('products.please_recharge_first') }}
                                </div>
                            `,
                            confirmButtonText: @json(__('dashboard.recharge')),
                            confirmButtonColor: '#003b7a',
                            showCancelButton: true,
                            cancelButtonText: @json(__('products.cancel')),
                        });

                        if (result.isConfirmed) {
                            window.location.href = @json(route('recharge.index'));
                        }

                        return;
                    }

                    const result = await Swal.fire({
                        icon: 'question',
                        title: @json(__('products.confirm_purchase_title')),
                        html: `
                            <div style="text-align:left; border-radius:16px; background:#fff8c7; padding:16px; color:#003b7a;">
                                <div style="font-weight:900; font-size:18px;">${name}</div>
                                <div style="margin-top:8px; font-weight:800;">
                                    {{ __('products.product_price') }}: {{ $currency }} ${amount}
                                </div>
                                <div style="margin-top:8px; font-size:13px; color:#4b5563;">
                                    {{ __('products.purchase_confirm_note') }}
                                </div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: @json(__('products.confirm_purchase_button')),
                        cancelButtonText: @json(__('products.cancel')),
                        confirmButtonColor: '#e4003a',
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true,
                        focusCancel: true,
                    });

                    if (result.isConfirmed) {
                        Swal.fire({
                            title: @json(__('products.processing')),
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            didOpen: () => {
                                Swal.showLoading();
                            },
                        });

                        form.submit();
                    }
                });
            });

            document.querySelectorAll('[data-product-detail]').forEach((button) => {
                button.addEventListener('click', async () => {
                    await Swal.fire({
                        title: button.dataset.name,
                        html: `
                            <div style="text-align:left; display:grid; gap:10px;">
                                <div style="border-radius:16px; background:#ffd900; padding:14px; color:#003b7a;">
                                    <div style="font-size:12px; font-weight:900; text-transform:uppercase;">${button.dataset.code}</div>
                                    <div style="font-size:26px; font-weight:900; margin-top:4px;">${button.dataset.price}</div>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.daily_income') }}</div>
                                        <div style="font-weight:900; color:#e4003a;">${button.dataset.daily}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.duration') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.days}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.total_income') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.total}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.estimated_profit') }}</div>
                                        <div style="font-weight:900; color:#e4003a;">${button.dataset.profit}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">ROI</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.roi}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.upgrade_bonus') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.upgrade}</div>
                                    </div>
                                </div>

                                <div style="border-radius:14px; border:2px dashed rgba(0,59,122,.2); padding:12px;">
                                    <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.ownership_status') }}</div>
                                    <div style="font-weight:900; color:#003b7a;">${button.dataset.owned}</div>
                                </div>
                            </div>
                        `,
                        confirmButtonText: @json(__('products.close')),
                        confirmButtonColor: '#003b7a',
                    });
                });
            });
        });
    </script>
@endpush
