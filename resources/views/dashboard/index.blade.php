@extends('layouts.app')

@section('title', __('dashboard.dashboard'))

@section('content')
    @php
        $now = now();
        $inviteUrl = url('/register?ref=' . $user->referral_code);
    @endphp

    <section class="overflow-hidden bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-[1.45fr_0.85fr] lg:items-center">
                <div>
                    <div
                         class="mb-3 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('dashboard.member_dashboard') }}
                    </div>

                    <h1
                        class="max-w-3xl text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('dashboard.hero_title') }}
                    </h1>

                    <p
                       class="mt-2 max-w-2xl overflow-hidden text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:text-base">
                        {{ __('dashboard.welcome_user', ['name' => $user->name]) }}
                        {{ __('dashboard.hero_description') }}
                    </p>

                    <div
                         x-data="{ inviteOpen: false }"
                         class="mt-5 grid grid-cols-2 gap-2 sm:mt-7 sm:flex sm:flex-wrap sm:gap-3">
                        <a
                           href="{{ route('products.index') }}"
                           class="rounded-full bg-[#e4003a] px-3 py-3 text-center text-[11px] font-black uppercase text-white shadow-lg hover:bg-[#c90032] sm:px-6 sm:text-sm">
                            {{ __('dashboard.buy_product') }}
                        </a>

                        <a
                           href="{{ route('recharge.index') }}"
                           class="rounded-full bg-[#003b7a] px-3 py-3 text-center text-[11px] font-black uppercase text-white shadow-lg hover:bg-[#002b59] sm:px-6 sm:text-sm">
                            {{ __('dashboard.recharge') }}
                        </a>

                        <a
                           href="{{ route('withdraw.index') }}"
                           class="rounded-full bg-white px-3 py-3 text-center text-[11px] font-black uppercase text-[#003b7a] shadow-lg hover:bg-white/80 sm:px-6 sm:text-sm">
                            {{ __('dashboard.withdraw') }}
                        </a>

                        <button
                                type="button"
                                class="rounded-full bg-[#2148ff] px-3 py-3 text-center text-[11px] font-black uppercase text-white shadow-lg hover:bg-[#1837d9] sm:px-6 sm:text-sm"
                                @click="inviteOpen = true">
                            {{ __('dashboard.invite_team') }}
                        </button>

                        @include('dashboard.partials.invite-modal', [
                            'user' => $user,
                            'inviteUrl' => $inviteUrl,
                        ])
                    </div>
                </div>

                <div
                     class="hidden rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-4 shadow-[7px_7px_0_#003b7a] sm:block sm:rounded-[2rem] sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs font-black uppercase text-[#e4003a] sm:text-sm">
                                {{ __('app.account_status') }}
                            </div>

                            <div class="mt-1 text-2xl font-black uppercase text-[#003b7a] sm:mt-2 sm:text-4xl">
                                {{ __('status.' . $user->status) }}
                            </div>

                            <div class="mt-1 break-all text-xs font-bold text-[#003b7a]/70 sm:mt-2 sm:text-sm">
                                {{ __('auth.username') }}: {{ '@' . $user->username }}
                            </div>
                        </div>

                        <div
                             class="shrink-0 rounded-full px-3 py-1.5 text-[10px] font-black uppercase sm:rounded-2xl sm:px-4 sm:py-2 sm:text-sm {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $user->status === 'active' ? __('status.active') : __('status.inactive') }}
                        </div>
                    </div>

                    <div class="mt-4 rounded-[1.25rem] bg-[#ffd900] p-4 sm:mt-6 sm:rounded-3xl sm:p-5">
                        <div class="text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-xs">
                            {{ __('dashboard.accumulated_recharge') }}
                        </div>

                        <div class="mt-1 break-words text-2xl font-black leading-none text-[#003b7a] sm:text-4xl">
                            {{ config('app.currency') }} {{ number_format((float) $user->accumulated_recharge, 2) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @if ($dashboardMarquees->isNotEmpty())
        <div class="overflow-hidden bg-[#003b7a] text-white">
            <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3 py-3">
                    <div
                         class="inline-flex shrink-0 items-center gap-2 rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-[#003b7a] shadow">
                        @include('partials.icon', [
                            'name' => 'speaker',
                            'class' => 'h-4 w-4',
                        ])

                        <span class="hidden sm:inline">
                            {{ __('dashboard.announcement') }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1 overflow-hidden">
                        <div class="dashboard-marquee whitespace-nowrap text-sm font-black text-white/90">
                            @foreach ($dashboardMarquees as $marquee)
                                @php
                                    $typeClass = match ($marquee->type) {
                                        'success' => 'text-green-300',
                                        'warning' => 'text-[#ffd900]',
                                        'danger' => 'text-red-300',
                                        default => 'text-blue-200',
                                    };

                                    $dotClass = match ($marquee->type) {
                                        'success' => 'bg-green-300',
                                        'warning' => 'bg-[#ffd900]',
                                        'danger' => 'bg-red-300',
                                        default => 'bg-blue-200',
                                    };
                                @endphp

                                <span class="mx-8 inline-flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full {{ $dotClass }}"></span>

                                    @if ($marquee->title)
                                        <span class="{{ $typeClass }}">
                                            {{ $marquee->title }}:
                                        </span>
                                    @endif

                                    <span>{{ $marquee->message }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <section class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <div class="rounded-[1.15rem] bg-[#003b7a] p-3 text-white shadow-lg sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-white/70 sm:text-sm">
                    {{ __('dashboard.recharge_wallet') }}
                </div>

                <div class="mt-2 break-words text-xl font-black leading-none sm:mt-3 sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $wallets['recharge'], 2) }}
                </div>

                <div class="mt-3 hidden text-[10px] font-bold text-white/70 sm:mt-4 sm:block sm:text-xs">
                    {{ __('dashboard.recharge_wallet_desc') }}
                </div>
            </div>

            <div class="rounded-[1.35rem] bg-[#e4003a] p-4 text-white shadow-lg sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-white/70 sm:text-sm">
                    {{ __('dashboard.withdrawable_wallet') }}
                </div>

                <div class="mt-2 break-words text-2xl font-black leading-none sm:mt-3 sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $wallets['withdrawable'], 2) }}
                </div>

                <div class="mt-3 hidden text-[10px] font-bold text-white/70 sm:mt-4 sm:block sm:text-xs">
                    {{ __('dashboard.withdrawable_wallet_desc') }}
                </div>
            </div>

            <div class="rounded-[1.35rem] bg-white p-4 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-sm">
                    {{ __('dashboard.today_income') }}
                </div>

                <div class="mt-2 break-words text-2xl font-black leading-none text-[#003b7a] sm:mt-3 sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $stats['today_daily_income'], 2) }}
                </div>

                <div class="mt-3 text-[10px] font-bold text-[#e4003a] sm:mt-4 sm:text-xs">
                    {{ __('dashboard.claimed_today') }}
                </div>
            </div>

            <div class="rounded-[1.35rem] bg-white p-4 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-sm">
                    {{ __('dashboard.commission') }}
                </div>

                <div class="mt-2 break-words text-2xl font-black leading-none text-[#003b7a] sm:mt-3 sm:text-4xl">
                    {{ config('app.currency') }} {{ number_format((float) $stats['total_commission'], 2) }}
                </div>

                <div class="mt-3 text-[10px] font-bold text-[#e4003a] sm:mt-4 sm:text-xs">
                    {{ __('dashboard.total_earned') }}
                </div>
            </div>
        </div>
        @include('dashboard.partials.marketing-widgets')
        <div class="mt-5 grid gap-5 lg:mt-8 lg:grid-cols-3 lg:gap-6">
            <div
                 class="rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6 lg:col-span-2 overflow-auto">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('dashboard.active_products') }}
                        </h2>

                        <p class="mt-1 text-xs font-bold text-[#003b7a]/60 sm:text-sm">
                            {{ __('dashboard.active_products_desc') }}
                        </p>
                    </div>

                    <a href="{{ route('products.index') }}"
                       class="shrink-0 rounded-full bg-[#ffd900] px-4 py-2 text-[11px] font-black uppercase text-[#003b7a] sm:px-5 sm:text-sm">
                        {{ __('dashboard.view_products') }}
                    </a>
                </div>

                @if ($activeProducts->isNotEmpty())
                    <div
                         class="mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain pb-3 [-webkit-overflow-scrolling:touch] sm:grid sm:grid-cols-2 sm:gap-4 sm:overflow-visible sm:pb-0 lg:gap-5">
                        @foreach ($activeProducts as $userProduct)
                            @php
                                $nextClaimAt = $userProduct->next_claim_at;

                                $canClaim =
                                    $userProduct->status === 'active' &&
                                    $nextClaimAt &&
                                    $nextClaimAt->lessThanOrEqualTo(now()) &&
                                    now()->lt($userProduct->effectiveExpiresAt());

                                $isWaiting =
                                    $userProduct->status === 'active' &&
                                    $nextClaimAt &&
                                    $nextClaimAt->greaterThan(now());

                                $totalDays = (int) $userProduct->duration_days_snapshot;
                                $claimedDays = (int) $userProduct->claimed_days;

                                $progressPercent =
                                    $totalDays > 0 ? min(100, round(($claimedDays / $totalDays) * 100)) : 0;
                            @endphp

                            <article
                                     class="w-[82vw] shrink-0 snap-start rounded-[1.25rem] border border-[#003b7a]/10 bg-[#fff8c7] p-3 shadow-sm sm:w-auto sm:shrink sm:rounded-[1.5rem] sm:p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate text-base font-black leading-tight text-[#003b7a] sm:text-lg">
                                            {{ $userProduct->product?->name ?? __('dashboard.product') }}
                                        </div>

                                        <div class="mt-1 text-[11px] font-bold text-[#003b7a]/60">
                                            {{ __('dashboard.claimed_days_progress', [
                                                'claimed' => $claimedDays,
                                                'total' => $totalDays,
                                            ]) }}
                                        </div>
                                    </div>

                                    <div
                                         class="shrink-0 rounded-full bg-[#003b7a] px-2.5 py-1 text-[10px] font-black uppercase text-white">
                                        {{ __('status.' . $userProduct->status) }}
                                    </div>
                                </div>

                                <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
                                    <div class="h-full rounded-full bg-[#e4003a]" style="width: {{ $progressPercent }}%">
                                    </div>
                                </div>

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <div class="rounded-2xl bg-white p-2.5 sm:p-3">
                                        <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                            {{ __('dashboard.price') }}
                                        </div>

                                        <div class="mt-1 text-base font-black text-[#003b7a]">
                                            {{ config('app.currency') }}
                                            {{ number_format((float) $userProduct->price_snapshot, 2) }}
                                        </div>
                                    </div>

                                    <div class="rounded-2xl bg-white p-2.5 sm:p-3">
                                        <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                            {{ __('dashboard.daily') }}
                                        </div>

                                        <div class="mt-1 text-base font-black text-[#e4003a]">
                                            {{ config('app.currency') }}
                                            {{ number_format((float) $userProduct->daily_income_snapshot, 2) }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2 rounded-2xl bg-white p-2.5 sm:p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('dashboard.next_claim') }}
                                    </div>

                                    <div class="mt-1 text-xs font-black text-[#003b7a]">
                                        {{ $nextClaimAt?->format('d M Y, h:i A') ?? '-' }}
                                    </div>
                                </div>

                                @if ($canClaim)
                                    <form
                                          method="POST"
                                          action="{{ route('daily-income.claim', $userProduct) }}"
                                          class="js-daily-claim-form mt-3"
                                          data-product="{{ $userProduct->product?->name ?? __('dashboard.product') }}"
                                          data-amount="{{ number_format((float) $userProduct->daily_income_snapshot, 2) }}">
                                        @csrf

                                        <button
                                                type="submit"
                                                class="w-full rounded-2xl bg-[#e4003a] px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-[#c90032] sm:text-sm">
                                            {{ __('dashboard.claim_daily_income') }}
                                        </button>
                                    </form>
                                @else
                                    <button
                                            type="button"
                                            disabled
                                            class="mt-3 w-full cursor-not-allowed rounded-2xl bg-gray-200 px-4 py-3 text-xs font-black uppercase text-gray-500 sm:text-sm">
                                        @if ($isWaiting)
                                            {{ __('dashboard.claim_not_ready') }}
                                        @else
                                            {{ __('dashboard.claim_unavailable') }}
                                        @endif
                                    </button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="mt-5 rounded-[1.25rem] bg-[#fff8c7] p-6 text-center sm:rounded-[1.5rem] sm:p-8">
                        <div class="text-xl font-black text-[#003b7a] sm:text-2xl">
                            {{ __('dashboard.no_active_product') }}
                        </div>

                        <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                            {{ __('dashboard.no_active_product_desc') }}
                        </p>

                        <a href="{{ route('products.index') }}"
                           class="mt-5 inline-flex rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white">
                            {{ __('dashboard.buy_product') }}
                        </a>
                    </div>
                @endif
            </div>

            <div class="space-y-5 lg:space-y-6">

                {{-- Desktop Quick Stats --}}
                <div
                     class="hidden rounded-[1.5rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6 lg:block">
                    <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('dashboard.quick_stats') }}
                    </h2>

                    <div class="mt-5 space-y-3">
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-4">
                            <span class="text-xs font-black uppercase text-[#003b7a]/70 sm:text-sm">
                                {{ __('dashboard.active_products') }}
                            </span>

                            <span class="text-lg font-black text-[#003b7a] sm:text-xl">
                                {{ $stats['active_product_count'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-4">
                            <span class="text-xs font-black uppercase text-[#003b7a]/70 sm:text-sm">
                                {{ __('dashboard.direct_team') }}
                            </span>

                            <span class="text-lg font-black text-[#003b7a] sm:text-xl">
                                {{ $stats['direct_team_count'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-4">
                            <span class="text-xs font-black uppercase text-[#003b7a]/70 sm:text-sm">
                                {{ __('dashboard.total_income') }}
                            </span>

                            <span class="text-right text-base font-black text-[#003b7a] sm:text-xl">
                                {{ config('app.currency') }} {{ number_format((float) $stats['total_daily_income'], 2) }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Mobile Quick Stats --}}
                <details class="rounded-[1.25rem] bg-white p-4 shadow-lg ring-1 ring-black/5 lg:hidden">
                    <summary class="cursor-pointer select-none text-sm font-black uppercase text-[#003b7a]">
                        {{ __('dashboard.quick_stats') }}
                    </summary>

                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-3">
                            <span class="text-xs font-black uppercase text-[#003b7a]/70">
                                {{ __('dashboard.active_products') }}
                            </span>

                            <span class="text-lg font-black text-[#003b7a]">
                                {{ $stats['active_product_count'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-3">
                            <span class="text-xs font-black uppercase text-[#003b7a]/70">
                                {{ __('dashboard.direct_team') }}
                            </span>

                            <span class="text-lg font-black text-[#003b7a]">
                                {{ $stats['direct_team_count'] }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-3">
                            <span class="text-xs font-black uppercase text-[#003b7a]/70">
                                {{ __('dashboard.total_income') }}
                            </span>

                            <span class="text-right text-base font-black text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $stats['total_daily_income'], 2) }}
                            </span>
                        </div>
                    </div>
                </details>

                {{-- Desktop Reminder Only --}}
                <div
                     class="hidden rounded-[1.5rem] bg-[#e4003a] p-5 text-white shadow-xl sm:rounded-[2rem] sm:p-6 lg:block">
                    <h2 class="text-xl font-black uppercase sm:text-2xl">
                        {{ __('dashboard.account_reminder') }}
                    </h2>

                    <p class="mt-2 text-sm font-bold text-white/80">
                        {{ __('dashboard.account_reminder_desc') }}
                    </p>
                </div>
            </div>
        </div>
        @include('partials.page-images', ['page' => 'dashboard'])
    </section>
    @include('dashboard.partials.account-policy-popup')
    @include('dashboard.partials.maintenance-notice-popup')
    @include('dashboard.partials.check-in-popup')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-daily-claim-form').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    if (typeof Swal === 'undefined') {
                        return;
                    }

                    event.preventDefault();

                    const product = form.dataset.product;
                    const amount = form.dataset.amount;

                    const result = await Swal.fire({
                        icon: 'question',
                        title: @json(__('dashboard.claim_confirm_title')),
                        html: `
                            <div style="text-align:left; border-radius:16px; background:#fff8c7; padding:16px; color:#003b7a;">
                                <div style="font-weight:900; font-size:18px;">${product}</div>
                                <div style="margin-top:8px; font-weight:800;">
                                    {{ __('dashboard.daily') }}: {{ config('app.currency') }} ${amount}
                                </div>
                                <div style="margin-top:8px; font-size:13px; color:#6b7280;">
                                    {{ __('dashboard.claim_confirm_note') }}
                                </div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: @json(__('dashboard.claim_confirm_button')),
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
        });
    </script>
@endpush
