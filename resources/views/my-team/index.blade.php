@extends('layouts.app')

@section('title', __('my_team.my_team'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');

        $levelCards = [
            [
                'key' => 'b',
                'label' => 'B',
                'rate' => $commissionRates->get(1)?->rate_percent ?? 0,
                'count' => $stats['b_count'],
                'active' => $stats['b_active_count'],
                'class' => 'from-[#ff3fac] to-[#e4003a]',
            ],
            [
                'key' => 'c',
                'label' => 'C',
                'rate' => $commissionRates->get(2)?->rate_percent ?? 0,
                'count' => $stats['c_count'],
                'active' => $stats['c_active_count'],
                'class' => 'from-[#22d3c5] to-[#009b8f]',
            ],
            [
                'key' => 'd',
                'label' => 'D',
                'rate' => $commissionRates->get(3)?->rate_percent ?? 0,
                'count' => $stats['d_count'],
                'active' => $stats['d_active_count'],
                'class' => 'from-[#3b82f6] to-[#003b7a]',
            ],
        ];

        $topStats = [
            [
                'label' => __('my_team.total_team_commission'),
                'value' => $currency . ' ' . number_format((float) $stats['total_team_commission'], 2),
                'icon' => 'salary',
                'class' => 'bg-[#003b7a] text-white',
            ],
            [
                'label' => __('my_team.valid_users'),
                'value' => number_format($stats['total_valid_users']) . ' / ' . number_format($stats['total_users']),
                'icon' => 'team',
                'class' => 'bg-white text-[#003b7a]',
            ],
            [
                'label' => __('my_team.team_accumulated_recharge'),
                'value' => $currency . ' ' . number_format((float) $stats['team_accumulated_recharge'], 2),
                'icon' => 'recharge',
                'class' => 'bg-[#e4003a] text-white',
            ],
        ];

        $timelineStats = [
            [
                'label' => __('my_team.today_joined'),
                'value' => number_format($stats['today_joined_count']) . ' ' . __('my_team.people'),
                'icon' => 'team',
            ],
            [
                'label' => __('my_team.yesterday_joined'),
                'value' => number_format($stats['yesterday_joined_count']) . ' ' . __('my_team.people'),
                'icon' => 'team',
            ],
            [
                'label' => __('my_team.today_recharge'),
                'value' => $currency . ' ' . number_format((float) $stats['today_recharge'], 2),
                'icon' => 'recharge',
            ],
            [
                'label' => __('my_team.yesterday_recharge'),
                'value' => $currency . ' ' . number_format((float) $stats['yesterday_recharge'], 2),
                'icon' => 'recharge',
            ],
            [
                'label' => __('my_team.this_month_recharge'),
                'value' => $currency . ' ' . number_format((float) $stats['this_month_recharge'], 2),
                'icon' => 'wallet',
            ],
            [
                'label' => __('my_team.last_month_recharge'),
                'value' => $currency . ' ' . number_format((float) $stats['last_month_recharge'], 2),
                'icon' => 'wallet',
            ],
        ];
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#003b7a] shadow sm:px-5 sm:py-2 sm:text-xs sm:tracking-[0.22em]">
                        {{ __('my_team.team_center') }}
                    </div>

                    <h1
                        class="mt-3 max-w-3xl text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:mt-4 sm:text-6xl lg:text-7xl">
                        {{ __('my_team.my_team') }}
                    </h1>

                    <p
                       class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/75 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('my_team.hero_desc') }}
                    </p>
                </div>

                <div
                     x-data="{
                         copiedCode: false,
                         copiedLink: false,
                     
                         copy(text, type) {
                             const done = () => {
                                 if (type === 'code') {
                                     this.copiedCode = true;
                                     setTimeout(() => this.copiedCode = false, 1600);
                                 } else {
                                     this.copiedLink = true;
                                     setTimeout(() => this.copiedLink = false, 1600);
                                 }
                             };
                     
                             if (navigator.clipboard && window.isSecureContext) {
                                 navigator.clipboard.writeText(text).then(done).catch(() => this.fallbackCopy(text, done));
                                 return;
                             }
                     
                             this.fallbackCopy(text, done);
                         },
                     
                         fallbackCopy(text, done) {
                             const input = document.createElement('textarea');
                             input.value = text;
                             input.setAttribute('readonly', '');
                             input.style.position = 'fixed';
                             input.style.opacity = '0';
                     
                             document.body.appendChild(input);
                             input.select();
                     
                             try {
                                 document.execCommand('copy');
                                 done();
                             } catch (error) {
                                 alert(text);
                             }
                     
                             document.body.removeChild(input);
                         }
                     }"
                     class="overflow-hidden rounded-[1.35rem] border-3 border-[#003b7a] bg-white shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="bg-[#003b7a] px-4 py-3 text-white sm:px-6 sm:py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div
                                     class="text-[10px] font-black uppercase tracking-[0.18em] text-[#ffd900] sm:tracking-[0.22em]">
                                    {{ __('my_team.invitation') }}
                                </div>

                                <h2 class="mt-1 truncate text-xl font-black uppercase sm:text-2xl">
                                    {{ __('my_team.invite_team') }}
                                </h2>
                            </div>

                            <div
                                 class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-[#ffd900] text-[#003b7a] sm:h-11 sm:w-11">
                                @include('partials.icon', [
                                    'name' => 'team',
                                    'class' => 'h-5 w-5 sm:h-6 sm:w-6',
                                ])
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 bg-white p-4 sm:p-6 lg:grid-cols-[170px_1fr] lg:items-start">
                        <div
                             class="mx-auto w-full max-w-[150px] rounded-[1.25rem] bg-[#fff8c7] p-2 ring-1 ring-[#003b7a]/10 sm:max-w-[190px] sm:rounded-[1.5rem] sm:p-3">
                            <div class="rounded-xl bg-white p-1.5 [&_svg]:h-auto [&_svg]:w-full sm:rounded-2xl sm:p-2">
                                {!! QrCode::format('svg')->size(180)->margin(1)->generate($inviteUrl) !!}
                            </div>

                            <div
                                 class="mt-1.5 text-center text-[9px] font-black uppercase tracking-wide text-[#003b7a]/60 sm:mt-2 sm:text-[10px]">
                                {{ __('dashboard.scan_to_register') }}
                            </div>
                        </div>

                        <div class="min-w-0 space-y-2 sm:space-y-3">
                            <div
                                 class="rounded-[1rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:rounded-[1.25rem] sm:p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div
                                             class="text-[9px] font-black uppercase tracking-[0.14em] text-[#003b7a]/55 sm:text-[10px]">
                                            {{ __('my_team.invite_code') }}
                                        </div>

                                        <div
                                             class="mt-1 break-all text-xl font-black leading-tight text-[#003b7a] sm:text-2xl">
                                            {{ $user->referral_code }}
                                        </div>
                                    </div>

                                    <button
                                            type="button"
                                            class="shrink-0 rounded-full bg-[#003b7a] px-3 py-1.5 text-[9px] font-black uppercase text-white sm:px-4 sm:py-2 sm:text-[10px]"
                                            @click="copy(@js($user->referral_code), 'code')"
                                            x-text="copiedCode ? @js(__('app.copied')) : @js(__('app.copy'))"></button>
                                </div>
                            </div>

                            <div
                                 class="rounded-[1rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:rounded-[1.25rem] sm:p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div
                                             class="text-[9px] font-black uppercase tracking-[0.14em] text-[#003b7a]/55 sm:text-[10px]">
                                            {{ __('my_team.invite_link') }}
                                        </div>

                                        <div
                                             class="mt-1 block max-w-full overflow-hidden text-ellipsis whitespace-nowrap text-xs font-black text-[#003b7a] sm:text-sm"
                                             title="{{ $inviteUrl }}">
                                            {{ $inviteUrl }}
                                        </div>
                                    </div>

                                    <button
                                            type="button"
                                            class="shrink-0 rounded-full bg-[#e4003a] px-3 py-1.5 text-[9px] font-black uppercase text-white sm:px-4 sm:py-2 sm:text-[10px]"
                                            @click="copy(@js($inviteUrl), 'link')"
                                            x-text="copiedLink ? @js(__('app.copied')) : @js(__('app.copy'))"></button>
                                </div>
                            </div>

                            <button
                                    type="button"
                                    class="w-full rounded-xl bg-[#ffd900] px-4 py-2.5 text-xs font-black uppercase text-[#003b7a] shadow-lg transition hover:-translate-y-0.5 sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm"
                                    @click="copy(@js($inviteUrl), 'link')"
                                    x-text="copiedLink ? @js(__('app.copied')) : @js(__('dashboard.copy_invite_link'))"></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                @foreach ($topStats as $item)
                    <div
                         class="rounded-[1rem] p-3 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-5 {{ $item['class'] }}">
                        <div class="flex items-center justify-between gap-2">
                            <div class="line-clamp-2 text-[8px] font-black uppercase leading-tight opacity-70 sm:text-xs">
                                {{ $item['label'] }}
                            </div>

                            @include('partials.icon', [
                                'name' => $item['icon'],
                                'class' => 'h-4 w-4 shrink-0 opacity-80 sm:h-5 sm:w-5',
                            ])
                        </div>

                        <div class="mt-2 break-words text-base font-black leading-tight sm:mt-3 sm:text-3xl">
                            {{ $item['value'] }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div
                 class="-mx-3 mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-2 [-webkit-overflow-scrolling:touch] sm:mx-0 sm:grid sm:grid-cols-3 sm:overflow-visible sm:px-0 sm:pb-0 sm:gap-4">
                @foreach ($levelCards as $card)
                    <a
                       href="{{ route('my-team.level', $card['key']) }}"
                       class="group w-[58vw] shrink-0 snap-start overflow-hidden rounded-[1.35rem] bg-gradient-to-br {{ $card['class'] }} p-4 text-white shadow-xl transition hover:-translate-y-1 hover:shadow-2xl sm:w-auto sm:shrink sm:rounded-[2rem] sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-4xl font-black leading-none sm:text-5xl">
                                    {{ $card['label'] }}
                                </div>

                                <div class="mt-1 text-[10px] font-black uppercase text-white/75 sm:mt-2 sm:text-sm">
                                    {{ __('my_team.commission_rate') }}
                                    {{ number_format((float) $card['rate'], 2) }}%
                                </div>
                            </div>

                            <div class="rounded-full bg-white/20 px-3 py-1.5 text-[10px] font-black sm:text-xs">
                                {{ __('my_team.view') }}
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3">
                            <div class="rounded-2xl bg-white/20 p-3 sm:p-4">
                                <div class="text-[9px] font-black uppercase text-white/60 sm:text-[10px]">
                                    {{ __('my_team.total_people') }}
                                </div>

                                <div class="mt-1 text-2xl font-black sm:text-3xl">
                                    {{ $card['count'] }}
                                </div>
                            </div>

                            <div class="rounded-2xl bg-white/20 p-3 sm:p-4">
                                <div class="text-[9px] font-black uppercase text-white/60 sm:text-[10px]">
                                    {{ __('my_team.valid_people') }}
                                </div>

                                <div class="mt-1 text-2xl font-black sm:text-3xl">
                                    {{ $card['active'] }}
                                </div>
                            </div>
                        </div>

                        <div
                             class="mt-3 rounded-xl bg-[#ffd900] px-4 py-2 text-center text-xs font-black uppercase text-[#003b7a] transition group-hover:bg-white sm:mt-5 sm:rounded-2xl sm:px-5 sm:py-3 sm:text-sm">
                            {{ __('my_team.check_detail') }}
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-4 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mt-5 sm:rounded-[2rem] sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('my_team.team_statistics') }}
                        </h2>

                        <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/55 sm:line-clamp-none sm:text-sm">
                            {{ __('my_team.team_statistics_desc') }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:gap-3 lg:grid-cols-3">
                    @foreach ($timelineStats as $item)
                        <div class="rounded-[1rem] bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:rounded-[1.5rem] sm:p-4">
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
                                {{ $item['value'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="mt-4 grid gap-3 sm:mt-5 sm:gap-4">
                <img
                     src="/lvl/rule_en.jpeg"
                     alt="{{ __('my_team.info_image_1') }}"
                     class="w-full rounded-[1.35rem] object-cover shadow-xl ring-1 ring-black/5 sm:rounded-[2rem]"
                     loading="lazy">

                <img
                     src="/lvl/rule_vi.jpeg"
                     alt="{{ __('my_team.info_image_2') }}"
                     class="w-full rounded-[1.35rem] object-cover shadow-xl ring-1 ring-black/5 sm:rounded-[2rem]"
                     loading="lazy">
            </div>
        </div>
    </section>
@endsection
