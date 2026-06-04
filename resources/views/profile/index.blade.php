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
            ['label' => __('navigation.team'), 'route' => 'team.index', 'icon' => 'team'],
            ['label' => __('navigation.contact'), 'route' => 'contact.index', 'icon' => 'contact'],
        ];

        $earningStats = [
            ['label' => __('profile.total_earning'), 'value' => $stats['total_earning'], 'icon' => 'salary'],
            ['label' => __('profile.income_today'), 'value' => $stats['income_today'], 'icon' => 'wallet'],
            ['label' => __('profile.yesterday_earnings'), 'value' => $stats['yesterday_earnings'], 'icon' => 'wallet'],
            ['label' => __('profile.team_income'), 'value' => $stats['team_income'], 'icon' => 'team'],
            [
                'label' => __('profile.daily_income_total'),
                'value' => $stats['daily_income_total'],
                'icon' => 'check-in',
            ],
            [
                'label' => __('profile.salary_income_total'),
                'value' => $stats['salary_income_total'],
                'icon' => 'salary',
            ],
        ];

        $rechargeNeed = $nextVipLevel
            ? max(0, (float) $nextVipLevel->required_accumulated_recharge - (float) $user->accumulated_recharge)
            : 0;

        $teamNeed = $nextVipLevel ? max(0, (int) $nextVipLevel->required_active_team_size - (int) $activeTeamSize) : 0;
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-5 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#003b7a] shadow">
                        {{ __('profile.member_profile') }}
                    </div>

                   <h1 class="mt-4 text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
    {{ __('profile.profile') }}
</h1>

                    <p class="mt-5 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/75 sm:text-base">
                        {{ __('profile.earning_overview_desc') }}
                    </p>
                </div>

                <div class="rounded-[2rem] border-4 border-[#003b7a] bg-white p-4 shadow-[10px_10px_0_#003b7a] sm:p-6">
                    <div class="flex items-center gap-4">
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
                                         class="h-24 w-24 rounded-[1.7rem] border-4 border-[#ffd900] bg-white object-cover shadow-lg transition group-hover:scale-[1.03] sm:h-32 sm:w-32">
                                @else
                                    <div
                                         class="grid h-24 w-24 place-items-center rounded-[1.7rem] border-4 border-[#ffd900] bg-white text-3xl font-black uppercase text-[#003b7a] shadow-lg transition group-hover:scale-[1.03] sm:h-32 sm:w-32 sm:text-4xl">
                                        {{ str($user->username)->substr(0, 2)->upper() }}
                                    </div>
                                @endif

                                <span
                                      class="absolute -bottom-2 -right-2 grid h-10 w-10 place-items-center rounded-2xl bg-[#003b7a] text-white shadow-lg ring-4 ring-white">
                                    @include('partials.icon', ['name' => 'profile', 'class' => 'h-5 w-5'])
                                </span>
                            </button>
                        </form>

                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-black uppercase tracking-[0.18em] text-[#e4003a]">
                                {{ __('profile.current_vip') }}
                            </div>

                            <h2 class="mt-2 break-words text-2xl font-black uppercase leading-none text-[#003b7a] sm:text-4xl">
    {{ $user->username }}
</h2>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span
                                      class="rounded-full bg-[#003b7a]/10 px-3 py-1.5 text-xs font-black uppercase text-[#003b7a]">
                                    {{ $user->name ?: __('profile.no_name') }}
                                </span>

                                <span
                                      class="rounded-full px-3 py-1.5 text-xs font-black uppercase {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ __('status.' . $user->status) }}
                                </span>

                                <span
                                      class="rounded-full bg-[#ffd900] px-3 py-1.5 text-xs font-black uppercase text-[#003b7a]">
                                    LV{{ $currentVipLevel?->level ?? 1 }}
                                </span>
                            </div>

                            @error('avatar')
                                <div class="mt-3 rounded-2xl bg-red-100 p-3 text-sm font-bold text-red-700">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-[1.25rem] bg-[#003b7a] p-4 text-white">
                            <div class="text-[10px] font-black uppercase text-white/60">
                                {{ __('profile.active_team') }}
                            </div>

                            <div class="mt-2 text-2xl font-black">
                                {{ $activeTeamSize }}
                            </div>
                        </div>

                        <div class="rounded-[1.25rem] bg-[#e4003a] p-4 text-white">
                            <div class="text-[10px] font-black uppercase text-white/60">
                                {{ __('profile.accumulated_recharge') }}
                            </div>

                            <div class="mt-2 break-words text-2xl font-black">
                                {{ $currency }} {{ number_format((float) $user->accumulated_recharge, 2) }}
                            </div>
                        </div>
                    </div>

                    @if ($nextVipLevel)
                        <div class="mt-3 rounded-[1.25rem] bg-[#fff8c7] p-4 text-sm font-black text-[#003b7a]">
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
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-4">
                <div class="rounded-[2rem] bg-[#003b7a] p-5 text-white shadow-xl">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-black uppercase text-white/65">
                            {{ __('profile.recharge_wallet') }}
                        </div>

                        @include('partials.icon', [
                            'name' => 'recharge',
                            'class' => 'h-5 w-5 text-[#ffd900]',
                        ])
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl">
                        {{ $currency }} {{ number_format((float) $stats['recharge_wallet_balance'], 2) }}
                    </div>

                    <p class="mt-3 text-sm font-bold text-white/65">
                        {{ __('dashboard.recharge_wallet_desc') ?? '' }}
                    </p>
                </div>

                <div class="rounded-[2rem] bg-[#e4003a] p-5 text-white shadow-xl">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-black uppercase text-white/65">
                            {{ __('profile.withdrawable_wallet') }}
                        </div>

                        @include('partials.icon', [
                            'name' => 'wallet',
                            'class' => 'h-5 w-5 text-[#ffd900]',
                        ])
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl">
                        {{ $currency }} {{ number_format((float) $stats['withdrawable_wallet_balance'], 2) }}
                    </div>

                    <p class="mt-3 text-sm font-bold text-white/65">
                        {{ __('dashboard.withdrawable_wallet_desc') ?? '' }}
                    </p>
                </div>

                <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('profile.total_earning') }}
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl text-[#003b7a]">
                        {{ $currency }} {{ number_format((float) $stats['total_earning'], 2) }}
                    </div>
                </div>

                <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('profile.total_withdrawals') }}
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl text-[#003b7a]">
                        {{ $currency }} {{ number_format((float) $stats['total_withdrawals'], 2) }}
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
                <div class="space-y-6">
                    <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                                    {{ __('profile.quick_actions') }}
                                </h2>

                                <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                                    {{ __('profile.quick_actions_desc') ?? '' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach ($quickActions as $action)
                                <a
                                   href="{{ route($action['route']) }}"
                                   class="group rounded-[1.5rem] bg-[#fff8c7] p-4 shadow-sm ring-1 ring-[#003b7a]/10 transition hover:-translate-y-0.5 hover:bg-[#ffd900] hover:shadow-lg">
                                    <div class="flex items-center justify-between">
                                        <div
                                             class="grid h-11 w-11 place-items-center rounded-2xl bg-[#003b7a] text-white transition group-hover:bg-white group-hover:text-[#003b7a]">
                                            @include('partials.icon', [
                                                'name' => $action['icon'],
                                                'class' => 'h-5 w-5',
                                            ])
                                        </div>

                                        <div
                                             class="text-xl font-black text-[#003b7a]/30 transition group-hover:translate-x-1 group-hover:text-[#003b7a]">
                                            →
                                        </div>
                                    </div>

                                    <div class="mt-4 text-sm font-black uppercase text-[#003b7a]">
                                        {{ $action['label'] }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                                    {{ __('profile.earning_overview') }}
                                </h2>

                                <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                                    {{ __('profile.earning_overview_desc') }}
                                </p>
                            </div>

                            <a
                               href="{{ route('wallet.index') }}"
                               class="inline-flex justify-center rounded-full bg-[#003b7a] px-5 py-2.5 text-xs font-black uppercase text-white">
                                {{ __('navigation.wallet') }}
                            </a>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ($earningStats as $item)
                                <div class="rounded-[1.35rem] bg-[#fff8c7] p-4 ring-1 ring-[#003b7a]/10">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                            {{ $item['label'] }}
                                        </div>

                                        @include('partials.icon', [
                                            'name' => $item['icon'],
                                            'class' => 'h-5 w-5 text-[#003b7a]',
                                        ])
                                    </div>

                                    <div class="mt-2 break-words text-xl font-black text-[#003b7a] sm:text-2xl">
                                        {{ $currency }} {{ number_format((float) $item['value'], 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <aside class="space-y-6">
                    <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                        <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('profile.account_overview') }}
                        </h2>

                        <div class="mt-5 space-y-3">
                            <div class="rounded-[1.35rem] bg-[#fff8c7] p-4">
                                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                    {{ __('profile.referral_code') }}
                                </div>

                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <div class="break-all text-lg font-black text-[#003b7a]">
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

                            <div class="rounded-[1.35rem] bg-[#fff8c7] p-4">
                                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                    {{ __('profile.pending_withdrawals') }}
                                </div>

                                <div class="mt-2 text-xl font-black text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $stats['pending_withdrawals'], 2) }}
                                </div>
                            </div>

                            <div class="rounded-[1.35rem] bg-[#fff8c7] p-4">
                                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                    {{ __('profile.unused_bonus_count') }}
                                </div>

                                <div class="mt-2 text-xl font-black text-[#003b7a]">
                                    {{ $stats['unused_bonus_count'] }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] bg-[#003b7a] p-5 text-white shadow-xl">
                        <div class="text-xs font-black uppercase tracking-[0.22em] text-[#ffd900]">
                            {{ __('profile.security_center') }}
                        </div>

                        <h2 class="mt-3 text-3xl font-black">
                            {{ __('profile.need_help') }}
                        </h2>

                        <p class="mt-2 text-sm font-bold leading-relaxed text-white/65">
                            {{ __('profile.support_desc') }}
                        </p>

                        <a
                           href="{{ route('contact.index') }}"
                           class="mt-5 inline-flex w-full items-center justify-center rounded-2xl bg-[#ffd900] px-5 py-3 text-sm font-black uppercase text-[#003b7a]">
                            {{ __('navigation.contact') }}
                        </a>
                    </div>

                    @if ($user->profile_photo_path)
                        <form method="POST" action="{{ route('profile.avatar.delete') }}">
                            @csrf
                            @method('DELETE')

                            <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-red-100 px-5 py-3 text-sm font-black uppercase text-red-700 shadow-lg">
                                {{ __('profile.remove_photo') }}
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button
                                type="submit"
                                class="w-full rounded-2xl bg-white px-5 py-3 text-sm font-black uppercase text-[#003b7a] shadow-lg ring-1 ring-black/5">
                            {{ __('app.logout') }}
                        </button>
                    </form>
                </aside>
            </div>
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
        </script>
    @endpush
@endsection
