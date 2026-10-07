@extends('layouts.app')

@section('title', __('profile.profile'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');

        $quickActions = [
            ['label' => __('navigation.recharge'), 'route' => 'recharge.index', 'icon' => 'recharge'],
            ['label' => __('navigation.withdraw'), 'route' => 'withdraw.index', 'icon' => 'withdraw'],
            ['label' => __('navigation.wallet'), 'route' => 'wallet.index', 'icon' => 'wallet'],
            ['label' => __('navigation.bonus_code'), 'route' => 'bonus-codes.index', 'icon' => 'bonus'],
            ['label' => __('navigation.salary'), 'route' => 'salary.index', 'icon' => 'salary'],
            ['label' => __('navigation.check_in'), 'route' => 'check-in.index', 'icon' => 'check-in'],
            ['label' => __('navigation.team'), 'route' => 'my-team.index', 'icon' => 'team'],
            ['label' => __('navigation.contact'), 'route' => 'contact.index', 'icon' => 'contact'],
            ['label' => __('navigation.calculator'), 'route' => 'calculator.index', 'icon' => 'calculator'],
            ['label' => __('navigation.downline_products'), 'route' => 'downline-products.index', 'icon' => 'team'],
            ['label' => __('navigation.points'), 'route' => 'points.index', 'icon' => 'bonus'],
            ['label' => __('navigation.change_password'), 'route' => 'settings.password.edit', 'icon' => 'profile'],
            [
                'label' => __('navigation.app_download'),
                'url' => $appDownloadUrl,
                'icon' => 'download',
                'external' => true,
                'enabled' => filled($appDownloadUrl),
            ],
        ];

        $walletCards = [
            [
                'label' => __('profile.recharge_wallet'),
                'value' => $stats['recharge_wallet_balance'],
                'icon' => 'recharge',
                'class' => 'bg-[#003b7a] text-white',
                'iconClass' => 'text-[#ffd900]',
                'desc' => __('dashboard.recharge_wallet_desc') ?? '',
            ],
            [
                'label' => __('profile.withdrawable_wallet'),
                'value' => $stats['withdrawable_wallet_balance'],
                'icon' => 'wallet',
                'class' => 'bg-[#e4003a] text-white',
                'iconClass' => 'text-[#ffd900]',
                'desc' => __('dashboard.withdrawable_wallet_desc') ?? '',
            ],
            [
                'label' => __('profile.total_earning'),
                'value' => $stats['total_earning'],
                'icon' => 'salary',
                'class' => 'bg-white text-[#003b7a]',
                'iconClass' => 'text-[#e4003a]',
                'desc' => '',
            ],
            [
                'label' => __('profile.total_withdrawals'),
                'value' => $stats['total_withdrawals'],
                'icon' => 'withdraw',
                'class' => 'bg-white text-[#003b7a]',
                'iconClass' => 'text-[#e4003a]',
                'desc' => '',
            ],
        ];

        $earningStats = [
            ['label' => __('profile.income_today'), 'value' => $stats['income_today'], 'icon' => 'wallet'],
            ['label' => __('profile.yesterday_earnings'), 'value' => $stats['yesterday_earnings'], 'icon' => 'wallet'],
            ['label' => __('profile.team_income'), 'value' => $stats['team_income'], 'icon' => 'team'],
            [
                'label' => __('profile.daily_income_total'),
                'value' => $stats['daily_income_total'],
                'icon' => 'check-in',
            ],
            // [
            //     'label' => __('profile.salary_income_total'),
            //     'value' => $stats['salary_income_total'],
            //     'icon' => 'salary',
            // ],
            ['label' => __('profile.total_earning'), 'value' => $stats['total_earning'], 'icon' => 'salary'],
        ];

        $accountStats = [
            [
                'label' => __('profile.pending_withdrawals'),
                'value' => $currency . ' ' . number_format((float) $stats['pending_withdrawals'], 2),
            ],
            [
                'label' => __('profile.unused_bonus_count'),
                'value' => $stats['unused_bonus_count'],
            ],
        ];

        $rechargeNeed = $nextVipLevel
            ? max(0, (float) $nextVipLevel->required_accumulated_recharge - (float) $stats['accumulated_recharge'])
            : 0;

        $teamNeed = $nextVipLevel ? max(0, (int) $nextVipLevel->required_active_team_size - (int) $activeTeamSize) : 0;

        $vipLevelNo = (int) ($displayVipLevelNo ?? ($currentVipLevel?->level ?? 1));
        $vipLevelLogo = asset('lvl/lvl-' . $vipLevelNo . '-removed-bg.png');
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#003b7a] shadow sm:px-5 sm:py-2 sm:text-xs sm:tracking-[0.22em]">
                        {{ __('profile.member_profile') }}
                    </div>

                    <h1
                        class="mt-3 text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:mt-4 sm:text-6xl lg:text-7xl">
                        {{ __('profile.profile') }}
                    </h1>

                    <p
                       class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/75 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('profile.earning_overview_desc') }}
                    </p>
                </div>

                <div
                     class="relative overflow-hidden rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div
                         class="absolute right-3 top-3 z-10 grid h-16 w-16 place-items-center overflow-hidden rounded-2xl p-1 sm:right-5 sm:top-5 sm:h-28 sm:w-28">
                        <img
                             src="{{ $vipLevelLogo }}"
                             alt="LV{{ $vipLevelNo }}"
                             class="h-full w-full rounded-xl object-contain"
                             onerror="this.closest('div').style.display='none'">
                    </div>

                    <div class="flex items-center gap-3 pr-16 sm:gap-4 sm:pr-24">
                        <form
                              id="avatar-upload-form"
                              method="POST"
                              action="{{ route('profile.avatar.update') }}"
                              enctype="multipart/form-data"
                              class="shrink-0">
                            @csrf

                            <input
                                   id="avatar-input"
                                   type="file"
                                   name="avatar"
                                   accept="image/png,image/jpeg,image/webp"
                                   class="hidden"
                                   onchange="document.getElementById('avatar-upload-form').submit()">

                            <button
                                    type="button"
                                    onclick="document.getElementById('avatar-input').click()"
                                    class="group relative block"
                                    aria-label="{{ __('profile.upload_photo') }}">
                                @if ($user->profile_photo_url)
                                    <img
                                         src="{{ $user->profile_photo_url }}"
                                         alt="{{ $user->username }}"
                                         class="h-20 w-20 rounded-[1.35rem] bg-white object-cover transition group-hover:scale-[1.03] sm:h-32 sm:w-32 sm:rounded-[1.7rem]">
                                @else
                                    <div
                                         class="grid h-20 w-20 place-items-center rounded-[1.35rem] border-4 border-[#ffd900] bg-white text-2xl font-black uppercase text-[#003b7a] shadow-lg transition group-hover:scale-[1.03] sm:h-32 sm:w-32 sm:rounded-[1.7rem] sm:text-4xl">
                                        {{ str($user->username)->substr(0, 2)->upper() }}
                                    </div>
                                @endif

                                <span
                                      class="absolute -bottom-1.5 -right-1.5 grid h-8 w-8 place-items-center rounded-xl bg-[#003b7a] text-white shadow-lg ring-4 ring-white sm:-bottom-2 sm:-right-2 sm:h-10 sm:w-10 sm:rounded-2xl">
                                    @include('partials.icon', [
                                        'name' => 'profile',
                                        'class' => 'h-4 w-4 sm:h-5 sm:w-5',
                                    ])
                                </span>
                            </button>
                        </form>

                        <div class="min-w-0 flex-1">
                            <div
                                 class="text-[10px] font-black uppercase tracking-[0.14em] text-[#e4003a] sm:text-xs sm:tracking-[0.18em]">
                                {{ __('profile.current_vip') }}
                            </div>

                            <h2
                                class="mt-1.5 truncate text-2xl font-black uppercase leading-none text-[#003b7a] sm:mt-2 sm:text-4xl">
                                {{ $user->username }}
                            </h2>

                            <div class="mt-2 flex flex-wrap gap-1.5 sm:mt-3 sm:gap-2">
                                <span
                                      class="rounded-full bg-[#003b7a]/10 px-2.5 py-1 text-[10px] font-black uppercase text-[#003b7a] sm:px-3 sm:py-1.5 sm:text-xs">
                                    {{ $user->name ?: __('profile.no_name') }}
                                </span>

                                <span
                                      class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase sm:px-3 sm:py-1.5 sm:text-xs {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ __('status.' . $user->status) }}
                                </span>

                                <span
                                      class="rounded-full bg-[#ffd900] px-2.5 py-1 text-[10px] font-black uppercase text-[#003b7a] sm:px-3 sm:py-1.5 sm:text-xs">
                                    LV{{ $vipLevelNo }}
                                </span>
                            </div>

                            @error('avatar')
                                <div
                                     class="mt-2 rounded-2xl bg-red-100 p-2 text-xs font-bold text-red-700 sm:mt-3 sm:p-3 sm:text-sm">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3">
                        <div class="rounded-[1rem] bg-[#003b7a] p-3 text-white sm:rounded-[1.25rem] sm:p-4">
                            <div class="text-[9px] font-black uppercase text-white/60 sm:text-[10px]">
                                {{ __('profile.active_team') }}
                            </div>

                            <div class="mt-1.5 text-xl font-black sm:mt-2 sm:text-2xl">
                                {{ $activeTeamSize }}
                            </div>
                        </div>

                        <div class="rounded-[1rem] bg-[#e4003a] p-3 text-white sm:rounded-[1.25rem] sm:p-4">
                            <div class="text-[9px] font-black uppercase text-white/60 sm:text-[10px]">
                                {{ __('profile.accumulated_recharge') }}
                            </div>

                            <div class="mt-1.5 break-words text-xl font-black sm:mt-2 sm:text-2xl">
                                {{ $currency }} {{ number_format((float) $stats['accumulated_recharge'], 2) }}
                            </div>
                        </div>
                    </div>

                    @if ($nextVipLevel)
                        <div
                             class="mt-2 rounded-[1rem] bg-[#fff8c7] p-3 text-xs font-black text-[#003b7a] sm:mt-3 sm:rounded-[1.25rem] sm:p-4 sm:text-sm">
                            {{ __('profile.next_level') }} LV{{ $nextVipLevel->level }}:
                            <span class="text-[#e4003a]">{{ $currency }} {{ number_format($rechargeNeed, 2) }}</span>
                            ·
                            <span class="text-[#e4003a]">{{ $teamNeed }}</span>
                            {{ __('profile.team_members') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid grid-cols-2 gap-2 sm:gap-5 lg:grid-cols-4">
                @foreach ($walletCards as $card)
                    <div
                         class="rounded-[1rem] p-3 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-5 {{ $card['class'] }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="line-clamp-2 text-[9px] font-black uppercase leading-tight opacity-70 sm:text-xs">
                                {{ $card['label'] }}
                            </div>

                            @include('partials.icon', [
                                'name' => $card['icon'],
                                'class' => 'h-4 w-4 shrink-0 sm:h-5 sm:w-5 ' . $card['iconClass'],
                            ])
                        </div>

                        <div class="mt-2 break-words text-xl font-black leading-none sm:mt-4 sm:text-4xl">
                            {{ $currency }} {{ number_format((float) $card['value'], 2) }}
                        </div>

                        @if ($card['desc'])
                            <p class="mt-3 hidden text-sm font-bold opacity-65 sm:block">
                                {{ $card['desc'] }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4 grid gap-4 xl:mt-6 xl:grid-cols-[1fr_360px]">
                <div class="space-y-4 xl:space-y-6">
                    <div class="rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                        <div>
                            <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                                {{ __('profile.quick_actions') }}
                            </h2>

                            <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/55 sm:line-clamp-none sm:text-sm">
                                {{ __('profile.quick_actions_desc') ?? '' }}
                            </p>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2 sm:mt-5 sm:grid-cols-4 sm:gap-3">
                            @foreach ($quickActions as $action)
                                <a
                                   href="{{ isset($action['url']) ? $action['url'] : route($action['route']) }}"
                                   @if ($action['external'] ?? false) target="_blank"
                                    rel="noopener noreferrer" @endif

                                   class="group rounded-[1rem] bg-[#fff8c7] p-3 text-center shadow-sm ring-1 ring-[#003b7a]/10 transition hover:-translate-y-0.5 hover:bg-[#ffd900] hover:shadow-lg sm:rounded-[1.5rem] sm:p-4 sm:text-left">
                                    <div
                                         class="mx-auto grid h-10 w-10 place-items-center rounded-xl bg-[#003b7a] text-white transition group-hover:bg-white group-hover:text-[#003b7a] sm:mx-0 sm:h-11 sm:w-11 sm:rounded-2xl">
                                        @include('partials.icon', [
                                            'name' => $action['icon'],
                                            'class' => 'h-5 w-5',
                                        ])
                                    </div>

                                    <div
                                         class="mt-2 truncate text-[10px] font-black uppercase text-[#003b7a] sm:mt-4 sm:text-sm">
                                        {{ $action['label'] }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                                    {{ __('profile.earning_overview') }}
                                </h2>

                                <p
                                   class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/55 sm:line-clamp-none sm:text-sm">
                                    {{ __('profile.earning_overview_desc') }}
                                </p>
                            </div>

                            <a
                               href="{{ route('wallet.index') }}"
                               class="shrink-0 rounded-full bg-[#003b7a] px-4 py-2 text-[10px] font-black uppercase text-white sm:px-5 sm:py-2.5 sm:text-xs">
                                {{ __('navigation.wallet') }}
                            </a>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3 xl:grid-cols-3">
                            @foreach ($earningStats as $item)
                                <div
                                     class="rounded-[1rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:rounded-[1.35rem] sm:p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div
                                             class="line-clamp-2 text-[9px] font-black uppercase leading-tight text-[#003b7a]/60 sm:text-[10px]">
                                            {{ $item['label'] }}
                                        </div>

                                        @include('partials.icon', [
                                            'name' => $item['icon'],
                                            'class' => 'h-4 w-4 shrink-0 text-[#003b7a] sm:h-5 sm:w-5',
                                        ])
                                    </div>

                                    <div class="mt-1.5 break-words text-base font-black text-[#003b7a] sm:mt-2 sm:text-2xl">
                                        {{ $currency }} {{ number_format((float) $item['value'], 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <aside class="space-y-4 xl:space-y-6">
                    @if ($svipPackage)
                        <div class="rounded-[1.35rem] bg-[#111827] p-4 text-white shadow-xl sm:rounded-[2rem] sm:p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div
                                         class="inline-flex rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#003b7a]">
                                        {{ __('svip.svip') }}
                                    </div>

                                    <h2 class="mt-2 text-lg font-black uppercase sm:mt-3 sm:text-2xl">
                                        {{ __('svip.auto_claim_title') }}
                                    </h2>

                                    <p
                                       class="mt-1 line-clamp-2 text-xs font-bold leading-relaxed text-white/65 sm:mt-2 sm:line-clamp-none sm:text-sm">
                                        {{ __('svip.auto_claim_desc') }}
                                    </p>
                                </div>

                                <div
                                     class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white/10 text-[#ffd900] sm:h-12 sm:w-12 sm:rounded-2xl">
                                    @include('partials.icon', [
                                        'name' => 'salary',
                                        'class' => 'h-5 w-5 sm:h-6 sm:w-6',
                                    ])
                                </div>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2 sm:mt-4 sm:gap-3">
                                <div class="rounded-xl bg-white/10 p-3 sm:rounded-2xl sm:p-4">
                                    <div class="text-[9px] font-black uppercase text-white/50 sm:text-[10px]">
                                        {{ __('svip.price') }}
                                    </div>

                                    <div class="mt-1 break-words text-xl font-black text-[#ffd900] sm:text-2xl">
                                        {{ $currency }} {{ number_format((float) $svipPackage->price, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-xl bg-white/10 p-3 sm:rounded-2xl sm:p-4">
                                    <div class="text-[9px] font-black uppercase text-white/50 sm:text-[10px]">
                                        {{ __('svip.duration') }}
                                    </div>

                                    <div class="mt-1 text-base font-black text-white sm:text-lg">
                                        @if ($svipPackage->duration_days)
                                            {{ $svipPackage->duration_days }} {{ __('svip.days') }}
                                        @else
                                            {{ __('svip.lifetime') }}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($activeSvipPurchase)
                                <div
                                     class="mt-3 rounded-xl bg-green-500/15 p-3 text-xs font-black text-green-200 sm:mt-4 sm:rounded-2xl sm:p-4 sm:text-sm">
                                    {{ __('svip.active') }}

                                    @if ($activeSvipPurchase->expires_at)
                                        <div class="mt-1 text-[10px] text-green-100/80 sm:text-xs">
                                            {{ __('svip.expires_at') }}:
                                            {{ $activeSvipPurchase->expires_at->format('d M Y, h:i A') }}
                                        </div>
                                    @else
                                        <div class="mt-1 text-[10px] text-green-100/80 sm:text-xs">
                                            {{ __('svip.lifetime') }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                @php
                                    $svipPrice = (float) $svipPackage->price;
                                    $canBuySvipWithRecharge = (float) $stats['recharge_wallet_balance'] >= $svipPrice;
                                    $canBuySvipWithWithdrawable =
                                        (float) $stats['withdrawable_wallet_balance'] >= $svipPrice;
                                    $canBuySvip = $canBuySvipWithRecharge || $canBuySvipWithWithdrawable;
                                @endphp

                                <form
                                      method="POST"
                                      action="{{ route('svip.purchase', $svipPackage) }}"
                                      class="js-svip-purchase-form mt-3 sm:mt-4"
                                      data-can-afford="{{ $canBuySvip ? '1' : '0' }}"
                                      data-price="{{ number_format($svipPrice, 2) }}"
                                      data-recharge-balance="{{ number_format((float) $stats['recharge_wallet_balance'], 2) }}"
                                      data-withdrawable-balance="{{ number_format((float) $stats['withdrawable_wallet_balance'], 2) }}">
                                    @csrf

                                    <select
                                            name="wallet_type"
                                            class="mb-2 w-full rounded-xl border border-white/15 bg-white/10 px-3 py-2.5 text-xs font-black text-white outline-none sm:rounded-2xl sm:px-4 sm:py-3 sm:text-sm">
                                        <option value="recharge" class="text-[#003b7a]" @disabled(!$canBuySvipWithRecharge)>
                                            {{ __('wallet.recharge_wallet') }} -
                                            {{ $currency }}
                                            {{ number_format((float) $stats['recharge_wallet_balance'], 2) }}
                                        </option>

                                        <option value="withdrawable" class="text-[#003b7a]" @disabled(!$canBuySvipWithWithdrawable)>
                                            {{ __('wallet.withdrawable_wallet') }} -
                                            {{ $currency }}
                                            {{ number_format((float) $stats['withdrawable_wallet_balance'], 2) }}
                                        </option>
                                    </select>

                                    <button
                                            type="submit"
                                            @disabled(!$canBuySvip)
                                            class="w-full rounded-xl px-4 py-2.5 text-xs font-black uppercase shadow-lg transition sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm
            {{ $canBuySvip
                ? 'bg-[#ffd900] text-[#003b7a] hover:-translate-y-0.5 hover:shadow-xl'
                : 'cursor-not-allowed bg-gray-500 text-gray-300' }}">
                                        @if (!$canBuySvip)
                                            {{ __('svip.insufficient_balance') }}
                                        @else
                                            {{ __('svip.purchase_now') }}
                                        @endif
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                        <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('profile.account_overview') }}
                        </h2>

                        <div class="mt-4 space-y-2 sm:mt-5 sm:space-y-3">
                            <div class="rounded-[1rem] bg-[#fff8c7] p-3 sm:rounded-[1.35rem] sm:p-4">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-[10px]">
                                    {{ __('profile.referral_code') }}
                                </div>

                                <div class="mt-1.5 flex items-center justify-between gap-3 sm:mt-2">
                                    <div class="break-all text-base font-black text-[#003b7a] sm:text-lg">
                                        {{ $user->referral_code }}
                                    </div>

                                    <button
                                            type="button"
                                            data-copy-text="{{ $user->referral_code }}"
                                            data-copy-default="{{ __('app.copy') }}"
                                            data-copy-success="{{ __('app.copied') }}"
                                            onclick="copyToClipboard(this)"
                                            class="shrink-0 rounded-full bg-[#003b7a] px-3 py-2 text-[10px] font-black uppercase text-white transition hover:bg-[#e4003a]">
                                        {{ __('app.copy') }}
                                    </button>
                                </div>
                            </div>

                            @foreach ($accountStats as $item)
                                <div
                                     class="flex items-center justify-between gap-3 rounded-[1rem] bg-[#fff8c7] p-3 sm:rounded-[1.35rem] sm:p-4">
                                    <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-[10px]">
                                        {{ $item['label'] }}
                                    </div>

                                    <div class="text-right text-base font-black text-[#003b7a] sm:text-xl">
                                        {{ $item['value'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-[1.35rem] bg-[#003b7a] p-4 text-white shadow-xl sm:rounded-[2rem] sm:p-5">
                        <div
                             class="text-[10px] font-black uppercase tracking-[0.18em] text-[#ffd900] sm:text-xs sm:tracking-[0.22em]">
                            {{ __('profile.security_center') }}
                        </div>

                        <h2 class="mt-2 text-2xl font-black sm:mt-3 sm:text-3xl">
                            {{ __('profile.need_help') }}
                        </h2>

                        <p
                           class="mt-1 line-clamp-2 text-xs font-bold leading-relaxed text-white/65 sm:mt-2 sm:line-clamp-none sm:text-sm">
                            {{ __('profile.support_desc') }}
                        </p>

                        <a
                           href="{{ route('contact.index') }}"
                           class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-[#ffd900] px-4 py-2.5 text-xs font-black uppercase text-[#003b7a] sm:mt-5 sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm">
                            {{ __('navigation.contact') }}
                        </a>
                    </div>

                    <div class="grid gap-2">
                        @if ($user->profile_photo_path)
                            <form method="POST" action="{{ route('profile.avatar.delete') }}">
                                @csrf
                                @method('DELETE')

                                <button
                                        type="submit"
                                        class="w-full rounded-xl bg-red-100 px-4 py-2.5 text-xs font-black uppercase text-red-700 shadow-lg sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm">
                                    {{ __('profile.remove_photo') }}
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <button
                                    type="submit"
                                    class="w-full rounded-xl bg-white px-4 py-2.5 text-xs font-black uppercase text-[#003b7a] shadow-lg ring-1 ring-black/5 sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm">
                                {{ __('app.logout') }}
                            </button>
                        </form>
                    </div>
                </aside>
            </div>
            @include('partials.page-images', ['page' => 'profile'])
        </div>

    </section>

    <div
         id="copy-toast"
         class="pointer-events-none fixed left-1/2 top-20 z-[9999] hidden -translate-x-1/2 rounded-2xl bg-[#003b7a] px-5 py-3 text-sm font-black text-white shadow-2xl">
        {{ __('app.copied') }}
    </div>

    @push('scripts')
        <script>
            function copyToClipboard(button) {
                const text = button.dataset.copyText || '';
                const defaultText = button.dataset.copyDefault || 'Copy';
                const successText = button.dataset.copySuccess || 'Copied!';

                const showSuccess = function() {
                    button.textContent = successText;
                    button.classList.remove('bg-[#003b7a]');
                    button.classList.add('bg-green-600');

                    const toast = document.getElementById('copy-toast');

                    if (toast) {
                        toast.classList.remove('hidden');

                        window.clearTimeout(window.__copyToastTimer);

                        window.__copyToastTimer = window.setTimeout(function() {
                            toast.classList.add('hidden');
                        }, 1600);
                    }

                    window.setTimeout(function() {
                        button.textContent = defaultText;
                        button.classList.remove('bg-green-600');
                        button.classList.add('bg-[#003b7a]');
                    }, 1600);
                };

                const fallbackCopy = function() {
                    const textarea = document.createElement('textarea');

                    textarea.value = text;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';

                    document.body.appendChild(textarea);
                    textarea.select();

                    try {
                        document.execCommand('copy');
                        showSuccess();
                    } catch (error) {
                        alert(text);
                    }

                    document.body.removeChild(textarea);
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text)
                        .then(showSuccess)
                        .catch(fallbackCopy);

                    return;
                }

                fallbackCopy();
            }

            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.js-svip-purchase-form').forEach(function(form) {
                    form.addEventListener('submit', async function(event) {
                        if (typeof Swal === 'undefined') {
                            return;
                        }

                        event.preventDefault();

                        const canAfford = form.dataset.canAfford === '1';
                        const price = form.dataset.price;
                        const rechargeBalance = form.dataset.rechargeBalance;
                        const withdrawableBalance = form.dataset.withdrawableBalance;

                        if (!canAfford) {
                            await Swal.fire({
                                icon: 'warning',
                                title: @json(__('svip.insufficient_balance')),
                                html: `
                            <div style="font-weight:700; color:#003b7a;">
                                {{ __('wallet.recharge_wallet') }}: {{ $currency }} ${rechargeBalance}<br>
                                {{ __('wallet.withdrawable_wallet') }}: {{ $currency }} ${withdrawableBalance}
                            </div>
                        `,
                                confirmButtonText: @json(__('app.ok')),
                                confirmButtonColor: '#003b7a',
                            });

                            return;
                        }

                        const walletSelect = form.querySelector('select[name="wallet_type"]');
                        const walletLabel = walletSelect ?
                            walletSelect.options[walletSelect.selectedIndex].text :
                            '';

                        const result = await Swal.fire({
                            icon: 'question',
                            title: @json(__('svip.confirm_title')),
                            html: `
                        <div style="text-align:left; border-radius:16px; background:#fff8c7; padding:16px; color:#003b7a;">
                            <div style="font-weight:900; font-size:18px;">{{ __('svip.svip') }}</div>
                            <div style="margin-top:8px; font-weight:800;">
                                {{ __('svip.price') }}: {{ $currency }} ${price}
                            </div>
                            <div style="margin-top:8px; font-weight:800;">
                                {{ __('products.payment_wallet') }}: ${walletLabel}
                            </div>
                            <div style="margin-top:8px; font-size:13px; color:#4b5563;">
                                {{ __('svip.confirm_desc') }}
                            </div>
                        </div>
                    `,
                            showCancelButton: true,
                            confirmButtonText: @json(__('svip.purchase_now')),
                            cancelButtonText: @json(__('app.cancel')),
                            confirmButtonColor: '#e4003a',
                            cancelButtonColor: '#6b7280',
                            reverseButtons: true,
                        });

                        if (result.isConfirmed) {
                            Swal.fire({
                                title: @json(__('app.loading')),
                                allowOutsideClick: false,
                                allowEscapeKey: false,
                                didOpen: function() {
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
@endsection
