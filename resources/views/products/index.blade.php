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
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-[#003b7a] p-3 text-white">
                                    <div class="text-[9px] font-black uppercase text-white/70">
                                        {{ __('wallet.recharge_wallet') }}
                                    </div>

                                    <div class="mt-1 break-words text-lg font-black">
                                        {{ $currency }} {{ number_format((float) $rechargeBalance, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-[#e4003a] p-3 text-white">
                                    <div class="text-[9px] font-black uppercase text-white/70">
                                        {{ __('wallet.withdrawable_wallet') }}
                                    </div>

                                    <div class="mt-1 break-words text-lg font-black">
                                        {{ $currency }} {{ number_format((float) $withdrawableBalance, 2) }}
                                    </div>
                                </div>
                            </div>
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
                            $totalClaims = $userProduct->effectiveTotalClaims();
                            $claimIntervalHours = $userProduct->effectiveClaimIntervalHours();

                            $isCompleted = (int) $userProduct->claimed_days >= $totalClaims;
                            $isClaimable =
                                !$isCompleted &&
                                $userProduct->next_claim_at &&
                                $userProduct->next_claim_at->lte(now()) &&
                                now()->lt($userProduct->effectiveExpiresAt());
                        @endphp

                        <div
                             class="w-[82vw] shrink-0 snap-start rounded-[1.35rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:w-auto sm:shrink sm:rounded-[1.5rem] sm:p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-black text-[#003b7a] sm:text-base">
                                        {{ $userProduct->product?->name ?? data_get($userProduct->meta, 'product_name_snapshot', __('products.product')) }}
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
                                        {{ __('products.income_per_claim') }}
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
                                        {{ $userProduct->claimed_days }}/{{ $totalClaims }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-2.5 sm:p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('products.claim_interval') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#003b7a]">
                                        {{ $claimIntervalHours }} {{ __('products.hours') }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-2.5 sm:p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/55">
                                        {{ __('products.total_claims') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#003b7a]">
                                        {{ $totalClaims }}
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
            <div x-data="{ activeType: @js(data_get($productTypeTabs->first(), 'key')) }">
                <div class="mb-3 flex gap-2 overflow-x-auto pb-1 sm:mb-5 sm:flex-wrap sm:overflow-visible">
                    @foreach ($productTypeTabs as $tab)
                        <button
                                type="button"
                                class="shrink-0 rounded-full px-3 py-2 text-[10px] font-black uppercase transition sm:px-4 sm:text-xs"
                                :class="activeType === @js($tab['key'])
                                    ? 'bg-[#003b7a] text-white shadow-lg'
                                    : 'bg-white text-[#003b7a] ring-1 ring-[#003b7a]/15'"
                                @click="activeType = @js($tab['key'])">
                            {{ $tab['label'] }}
                            <span class="ml-1 opacity-70">({{ $tab['count'] }})</span>
                        </button>
                    @endforeach
                </div>

                <p class="mb-3 text-xs font-bold text-[#003b7a]/60 sm:mb-5 sm:text-sm">
                    {{ __('products.choose_product_type') }}
                </p>

                @foreach ($productsByType as $type => $typeProducts)
                    <div
                         x-show="activeType === @js($type)"
                         x-cloak
                         class="grid grid-cols-2 gap-2 sm:gap-5 md:grid-cols-3 xl:grid-cols-4">
                        @foreach ($typeProducts as $product)
                    @php
                        $canAffordRecharge = (float) $rechargeBalance >= (float) $product->price;
                        $canAffordWithdrawable = (float) $withdrawableBalance >= (float) $product->price;
                        $canAfford = $canAffordRecharge || $canAffordWithdrawable;

                        $hasOwnedBefore = in_array($product->id, $ownedProductIdsForUpgradeBonus, true);

                        $claimIntervalHours = $product->effectiveClaimIntervalHours();
                        $totalClaims = $product->effectiveTotalClaims();

                        $claimIncome = (float) $product->daily_income;
                        $totalIncome = $claimIncome * $totalClaims;
                        $profit = $totalIncome - (float) $product->price;
                        $roi = $product->price > 0 ? ($profit / (float) $product->price) * 100 : 0;

                        $purchasedCount = $userProductPurchaseCounts[$product->id] ?? 0;
                        $maxPurchase = $product->maxPurchasePerUser();
                        $limitReached = $purchasedCount >= $maxPurchase;

                        $isAvailableToday = $product->isAvailableToday();
                        $isWithinAvailablePeriod = $product->isWithinAvailablePeriod();
                        $hasStock = $product->hasStock() && $product->hasDailyStock();
                        $isPurchasableNow = $product->isPurchasableNow();
                        $meetsVipLevelRequirement = $product->meetsVipLevelRequirement($user);

                        $canPurchase = $canAfford && !$limitReached && $isPurchasableNow && $meetsVipLevelRequirement;

                        $remainingStock =
                            $product->stock_quantity === null
                                ? null
                                : max(0, (int) $product->stock_quantity - (int) $product->sold_quantity);

                        $availableFromLabel = $product->available_from?->format('H:i') ?? '-';
                        $availableUntilLabel = $product->available_until?->format('H:i') ?? '-';

                        $availableDaysLabel = collect($product->available_days ?: [])
                            ->map(fn($day) => \App\Models\Product::weekdayOptions()[(int) $day] ?? $day)
                            ->join(', ');

                        $availableDaysLabel = $availableDaysLabel ?: __('filament.everyday');
                    @endphp

                    <article
                             class="relative overflow-hidden rounded-[1rem] bg-white shadow-lg ring-1 ring-black/5 sm:rounded-[1.75rem]">
                        <div class="absolute right-0 top-0 h-10 w-10 rounded-bl-[0.9rem] bg-[#ffd900] sm:h-20 sm:w-20">
                        </div>

                        <div class="relative z-10 p-2.5 sm:p-5">
                            <div class="space-y-1.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div
                                             class="inline-flex max-w-full rounded-full bg-[#003b7a] px-2 py-0.5 text-[8px] font-black uppercase text-white sm:px-3 sm:py-1 sm:text-xs">
                                            <span class="truncate">{{ $product->code }}</span>
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

                                <div class="flex flex-wrap gap-1">
                                    <span
                                          class="rounded-full bg-[#fff8c7] px-2 py-0.5 text-[8px] font-black uppercase text-[#003b7a] ring-1 ring-[#003b7a]/10">
                                        {{ __('products.types.' . $product->product_type) !== 'products.types.' . $product->product_type
                                            ? __('products.types.' . $product->product_type)
                                            : str($product->product_type)->headline() }}
                                    </span>

                                    {{-- @if ($remainingStock !== null)
                                        <span
                                              class="rounded-full bg-[#e4003a] px-2 py-0.5 text-[8px] font-black uppercase text-white">
                                            {{ $remainingStock }} {{ __('products.left') }}
                                        </span>
                                    @endif --}}

                                    @if (!$isAvailableToday)
                                        <span
                                              class="rounded-full bg-gray-200 px-2 py-0.5 text-[8px] font-black uppercase text-gray-600">
                                            {{ __('products.not_available_today') }}
                                        </span>
                                    @elseif (!$isWithinAvailablePeriod)
                                        <span
                                              class="rounded-full bg-gray-200 px-2 py-0.5 text-[8px] font-black uppercase text-gray-600">
                                            {{ __('products.not_available_now') }}
                                        </span>
                                    @elseif (!$hasStock)
                                        <span
                                              class="rounded-full bg-gray-200 px-2 py-0.5 text-[8px] font-black uppercase text-gray-600">
                                            {{ __('products.sold_out') }}
                                        </span>
                                    @endif

                                    @if ($claimIntervalHours !== 24)
                                        <span
                                              class="rounded-full bg-[#003b7a] px-2 py-0.5 text-[8px] font-black uppercase text-white">
                                            {{ $claimIntervalHours }}{{ __('products.hours_short') }}
                                        </span>
                                    @endif
                                </div>
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

                            @if ($product->available_until)
                                <div class="mt-1.5 rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:mt-2 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.ends_at') }}
                                    </div>

                                    <div class="mt-0.5 text-[10px] font-black text-[#003b7a] sm:mt-1 sm:text-xs">
                                        {{ $product->available_until->format('H:i') }}
                                    </div>
                                </div>
                            @endif

                            <div class="mt-1.5 grid grid-cols-2 gap-1.5 sm:mt-2 sm:gap-2">
                                <div class="rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.income_per_claim') }}
                                    </div>

                                    <div class="mt-0.5 text-[11px] font-black text-[#e4003a] sm:mt-1 sm:text-sm">
                                        {{ $currency }} {{ number_format($claimIncome, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.claim_interval') }}
                                    </div>

                                    <div class="mt-0.5 text-[11px] font-black text-[#003b7a] sm:mt-1 sm:text-sm">
                                        {{ $claimIntervalHours }} {{ __('products.hours') }}
                                    </div>
                                </div>

                                <div class="rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.total_claims') }}
                                    </div>

                                    <div class="mt-0.5 text-[11px] font-black text-[#003b7a] sm:mt-1 sm:text-sm">
                                        {{ $totalClaims }}
                                    </div>
                                </div>

                                <div class="rounded-[0.75rem] bg-[#fff8c7] p-1.5 sm:rounded-xl sm:p-2.5">
                                    <div
                                         class="text-[7px] font-black uppercase leading-tight text-[#003b7a]/55 sm:text-[9px]">
                                        {{ __('products.total_income') }}
                                    </div>

                                    <div class="mt-0.5 text-[11px] font-black text-[#e4003a] sm:mt-1 sm:text-sm">
                                        {{ $currency }} {{ number_format($totalIncome, 2) }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-1 gap-1.5">
                                @if ($product->min_vip_level !== null)
                                    <p class="text-[9px] font-bold text-[#003b7a] sm:text-xs">
                                        {{ __('products.vip_level_required', ['level' => $product->min_vip_level]) }}
                                    </p>
                                @endif

                                <button
                                        type="button"
                                        class="w-full rounded-lg border border-[#003b7a]/15 bg-white px-2 py-1.5 text-[9px] font-black text-[#003b7a] sm:rounded-xl sm:px-2.5 sm:py-2 sm:text-[11px]"
                                        data-product-detail
                                        data-name="{{ $product->name }}"
                                        data-code="{{ $product->code }}"
                                        data-type="{{ __('products.types.' . $product->product_type) !== 'products.types.' . $product->product_type ? __('products.types.' . $product->product_type) : str($product->product_type)->headline() }}"
                                        data-price="{{ $currency }} {{ number_format((float) $product->price, 2) }}"
                                        data-income="{{ $currency }} {{ number_format($claimIncome, 2) }}"
                                        data-interval="{{ $claimIntervalHours }} {{ __('products.hours') }}"
                                        data-claims="{{ $totalClaims }}"
                                        data-duration="{{ $product->duration_days }} {{ __('products.days') }}"
                                        data-total="{{ $currency }} {{ number_format($totalIncome, 2) }}"
                                        data-profit="{{ $currency }} {{ number_format($profit, 2) }}"
                                        data-roi="+{{ number_format($roi, 0) }}%"
                                        data-upgrade="{{ $currency }} {{ number_format((float) $product->upgrade_bonus, 2) }}"
                                        data-points="{{ number_format((float) $product->extra_points, 2) }}"
                                        data-days="{{ $availableDaysLabel }}"
                                        data-from="{{ $availableFromLabel }}"
                                        data-until="{{ $availableUntilLabel }}"
                                        data-stock="{{ $remainingStock === null ? __('filament.unlimited') : $remainingStock }}"
                                        data-owned="{{ $hasOwnedBefore ? __('products.already_owned') : __('products.available') }}">
                                    {{ __('products.view_details') }}
                                </button>

                                <form
                                      method="POST"
                                      action="{{ route('products.purchase', $product) }}"
                                      class="js-purchase-form"
                                      data-can-order="{{ !$limitReached && $isPurchasableNow && $meetsVipLevelRequirement ? '1' : '0' }}"
                                      data-price="{{ $product->price }}"
                                      data-recharge="{{ $rechargeBalance }}"
                                      data-withdrawable="{{ $withdrawableBalance }}"
                                      data-can-afford="{{ $canAfford ? '1' : '0' }}"
                                      data-can-afford-recharge="{{ $canAffordRecharge ? '1' : '0' }}"
                                      data-can-afford-withdrawable="{{ $canAffordWithdrawable ? '1' : '0' }}"
                                      data-name="{{ $product->name }}"
                                      data-amount="{{ number_format((float) $product->price, 2) }}"
                                      data-recharge-balance="{{ number_format((float) $rechargeBalance, 2) }}"
                                      data-withdrawable-balance="{{ number_format((float) $withdrawableBalance, 2) }}">
                                    @csrf

                                    @if ($product->allow_coupons && $coupons->isNotEmpty())
                                        <label class="mb-1 block text-[10px] font-bold text-[#003b7a]">
                                            {{ __('golden_egg.purchase_coupon') }}
                                            <select name="coupon_id" class="mt-1 mb-2 w-full rounded-lg border border-[#003b7a]/20 bg-white px-2 py-2 text-[10px] sm:text-xs">
                                                <option value="" data-percent="0">{{ __('golden_egg.no_coupon') }}</option>
                                                @foreach ($coupons as $coupon)
                                                    <option value="{{ $coupon->id }}" data-percent="{{ $coupon->discount_percent }}">{{ __('golden_egg.percent_off', ['percent' => (float) $coupon->discount_percent]) }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <p class="mb-2 text-[10px] font-bold text-[#003b7a]">
                                            {{ __('golden_egg.payable') }}: {{ $currency }} <span data-payable>{{ number_format((float) $product->price, 2) }}</span>
                                        </p>
                                    @elseif (! $product->allow_coupons)
                                        <p class="mb-2 text-[10px] font-bold text-[#003b7a]">{{ __('golden_egg.product_coupons_disabled') }}</p>
                                    @endif

                                    <select
                                            name="wallet_type"
                                            class="mb-1.5 w-full rounded-lg border border-[#003b7a]/20 bg-[#fff8c7] px-2 py-1.5 text-[9px] font-black text-[#003b7a] outline-none sm:mb-2 sm:rounded-xl sm:px-3 sm:py-2 sm:text-xs">
                                        <option value="recharge" @disabled(!$canAffordRecharge)>
                                            {{ __('wallet.recharge_wallet') }} -
                                            {{ $currency }} {{ number_format((float) $rechargeBalance, 2) }}
                                        </option>

                                        <option value="withdrawable" @disabled(!$canAffordWithdrawable)>
                                            {{ __('wallet.withdrawable_wallet') }} -
                                            {{ $currency }} {{ number_format((float) $withdrawableBalance, 2) }}
                                        </option>
                                    </select>

                                    <button
                                            type="submit"
                                            @disabled(!$canPurchase)
                                            class="w-full rounded-lg px-2 py-2 text-[9px] font-black uppercase shadow-md transition sm:rounded-2xl sm:px-3 sm:py-3 sm:text-sm
                                        {{ $canPurchase
                                            ? 'bg-[#e4003a] text-white hover:-translate-y-0.5 hover:bg-[#c90032]'
                                            : 'cursor-not-allowed bg-gray-200 text-gray-500' }}">
                                        @if (!$hasStock)
                                            {{ __('products.sold_out') }}
                                        @elseif (!$meetsVipLevelRequirement)
                                            {{ __('products.vip_level_insufficient') }}
                                        @elseif ($limitReached)
                                            {{ __('products.purchase_limit_reached') }}
                                        @elseif (!$isPurchasableNow)
                                            {{ __('products.not_available_now') }}
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
        @include('partials.page-images', ['page' => 'products'])
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
                @if ($coupons->isNotEmpty())
                const couponSelect = form.querySelector('[name="coupon_id"]');
                if (couponSelect) {
                    const updatePrice = () => {
                        const cents = Math.round(Number(form.dataset.price) * 100);
                        const percent = Math.round(Number(couponSelect.selectedOptions[0].dataset.percent) * 100);
                        const price = Math.round(cents * (10000 - percent) / 10000) / 100;
                        const wallets = form.querySelector('[name="wallet_type"]');
                        for (const option of wallets.options) option.disabled = Number(form.dataset[option.value]) < price;
                        if (wallets.selectedIndex < 0 || wallets.selectedOptions[0]?.disabled) {
                            wallets.selectedIndex = Array.from(wallets.options).findIndex(option => !option.disabled);
                        }
                        const canAfford = Array.from(wallets.options).some(option => !option.disabled);
                        const canPurchase = canAfford && form.dataset.canOrder === '1';
                        form.dataset.canAfford = canAfford ? '1' : '0';
                        form.dataset.amount = price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        form.querySelector('[data-payable]').textContent = form.dataset.amount;
                        const button = form.querySelector('[type="submit"]');
                        button.disabled = !canPurchase;
                        button.classList.toggle('bg-[#e4003a]', canPurchase);
                        button.classList.toggle('text-white', canPurchase);
                        button.classList.toggle('bg-gray-200', !canPurchase);
                        button.classList.toggle('text-gray-500', !canPurchase);
                        button.classList.toggle('cursor-not-allowed', !canPurchase);
                        if (form.dataset.canOrder === '1') button.textContent = canAfford
                            ? @json(__('products.purchase_now')) : @json(__('products.insufficient_balance'));
                    };
                    couponSelect.addEventListener('change', updatePrice);
                    updatePrice();
                }
                @endif
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const canAfford = form.dataset.canAfford === '1';
                    const name = form.dataset.name;
                    const amount = form.dataset.amount;
                    const rechargeBalance = form.dataset.rechargeBalance;
                    const withdrawableBalance = form.dataset.withdrawableBalance;

                    if (!canAfford) {
                        const result = await Swal.fire({
                            icon: 'warning',
                            title: @json(__('products.insufficient_balance')),
                            html: `
                                <div style="font-weight:700; color:#003b7a;">
                                    {{ __('wallet.recharge_wallet') }}: {{ $currency }} ${rechargeBalance}<br>
                                    {{ __('wallet.withdrawable_wallet') }}: {{ $currency }} ${withdrawableBalance}
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

                    const walletSelect = form.querySelector('select[name="wallet_type"]');
                    const walletLabel = walletSelect ?
                        walletSelect.options[walletSelect.selectedIndex].text :
                        '';

                    const result = await Swal.fire({
                        icon: 'question',
                        title: @json(__('products.confirm_purchase_title')),
                        html: `
                            <div style="text-align:left; border-radius:16px; background:#fff8c7; padding:16px; color:#003b7a;">
                                <div style="font-weight:900; font-size:18px;">${name}</div>
                                <div style="margin-top:8px; font-weight:800;">
                                    {{ __('products.product_price') }}: {{ $currency }} ${amount}
                                </div>
                                <div style="margin-top:8px; font-weight:800;">
                                    {{ __('products.payment_wallet') }}: ${walletLabel}
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
                                    <div style="font-size:11px; font-weight:900; margin-top:4px; opacity:.75;">${button.dataset.type}</div>
                                    <div style="font-size:26px; font-weight:900; margin-top:4px;">${button.dataset.price}</div>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.income_per_claim') }}</div>
                                        <div style="font-weight:900; color:#e4003a;">${button.dataset.income}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.claim_interval') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.interval}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.total_claims') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.claims}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('products.duration') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.duration}</div>
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
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('filament.extra_points') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.points}</div>
                                    </div>

                                    <div style="border-radius:14px; background:#fff8c7; padding:12px;">
                                        <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('filament.available_days') }}</div>
                                        <div style="font-weight:900; color:#003b7a;">${button.dataset.days}</div>
                                    </div>

                                </div>

                                <div style="border-radius:14px; border:2px dashed rgba(0,59,122,.2); padding:12px;">
                                    <div style="font-size:11px; font-weight:900; color:#6b7280;">{{ __('filament.available_from') }} / {{ __('filament.available_until') }}</div>
                                    <div style="font-weight:900; color:#003b7a;">${button.dataset.from} → ${button.dataset.until}</div>
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
