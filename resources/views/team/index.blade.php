@extends('layouts.app')

@section('title', __('team.team'))

@section('content')
    @php
        $inviteUrl = url('/register?ref=' . $user->referral_code);

        $bCommissionRate = (float) data_get($commissionRates, '1.rate_percent', 0);
        $cCommissionRate = (float) data_get($commissionRates, '2.rate_percent', 0);
        $dCommissionRate = (float) data_get($commissionRates, '3.rate_percent', 0);

        $levelCards = [
            [
                'label' => __('team.b_level'),
                'short' => 'B',
                'route_level' => 1,
                'active_match' => ['1', 'b', 'B'],
                'total' => $stats['b_total'],
                'active' => $stats['b_active'],
                'rate' => $bCommissionRate,
                'class' => 'bg-white text-[#003b7a]',
                'badge' => 'bg-[#e4003a] text-white',
            ],
            [
                'label' => __('team.c_level'),
                'short' => 'C',
                'route_level' => 2,
                'active_match' => ['2', 'c', 'C'],
                'total' => $stats['c_total'],
                'active' => $stats['c_active'],
                'rate' => $cCommissionRate,
                'class' => 'bg-white text-[#003b7a]',
                'badge' => 'bg-[#00a99d] text-white',
            ],
            [
                'label' => __('team.d_level'),
                'short' => 'D',
                'route_level' => 3,
                'active_match' => ['3', 'd', 'D'],
                'total' => $stats['d_total'],
                'active' => $stats['d_active'],
                'rate' => $dCommissionRate,
                'class' => 'bg-white text-[#003b7a]',
                'badge' => 'bg-[#2148ff] text-white',
            ],
            [
                'label' => __('team.all_downline'),
                'short' => 'ALL',
                'route_level' => 'all',
                'active_match' => ['all'],
                'total' => $stats['all_total'],
                'active' => $stats['all_active'],
                'rate' => null,
                'class' => 'bg-[#003b7a] text-white',
                'badge' => 'bg-[#ffd900] text-[#003b7a]',
            ],
        ];
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div>
                    <div class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('team.team_center') }}
                    </div>

                    <h1 class="text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('team.hero_title') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('team.hero_description') }}
                    </p>
                </div>

                <div
                    x-data="{
                        copied: false,
                        copy() {
                            const text = @js($inviteUrl);

                            const done = () => {
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1600);
                            };

                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text).then(done);
                                return;
                            }

                            const input = document.createElement('textarea');
                            input.value = text;
                            input.style.position = 'fixed';
                            input.style.opacity = '0';
                            document.body.appendChild(input);
                            input.select();
                            document.execCommand('copy');
                            document.body.removeChild(input);
                            done();
                        }
                    }"
                    class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-sm">
                                {{ __('team.your_referral_code') }}
                            </div>

                            <div class="mt-1 break-all text-2xl font-black leading-none text-[#003b7a] sm:mt-2 sm:text-4xl">
                                {{ $user->referral_code }}
                            </div>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-full bg-[#e4003a] px-4 py-2 text-[10px] font-black uppercase text-white shadow-lg sm:px-5 sm:py-3 sm:text-sm"
                            @click="copy()"
                            x-text="copied ? '{{ __('team.copied') }}' : '{{ __('app.copy') }}'"
                        ></button>
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 rounded-2xl bg-[#fff8c7] p-3">
                        <div class="min-w-0 truncate text-xs font-bold text-[#003b7a]/70" title="{{ $inviteUrl }}">
                            {{ $inviteUrl }}
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-full bg-[#003b7a] px-3 py-1.5 text-[10px] font-black uppercase text-white"
                            @click="copy()"
                        >
                            {{ __('team.copy_invite_link') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="-mx-3 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-3 pb-2 [-webkit-overflow-scrolling:touch] sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0 sm:pb-0 sm:gap-4">
            @foreach ($levelCards as $card)
                @php
                    $isActiveCard = in_array((string) $level, array_map('strval', $card['active_match']), true);
                @endphp

                <a
                    href="{{ route('team.index', ['level' => $card['route_level'], 'status' => $status]) }}"
                    class="w-[42vw] shrink-0 snap-start rounded-[1.15rem] p-3 shadow-lg ring-1 ring-black/5 transition hover:-translate-y-0.5 sm:w-auto sm:shrink sm:rounded-[1.35rem] sm:p-4 {{ $card['class'] }} {{ $isActiveCard ? 'outline outline-4 outline-[#ffd900]' : '' }}"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-[10px] font-black uppercase opacity-65">
                            {{ $card['label'] }}
                        </div>

                        <div class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $card['badge'] }}">
                            {{ $card['short'] }}
                        </div>
                    </div>

                    <div class="mt-2 text-2xl font-black leading-none sm:text-3xl">
                        {{ $card['total'] }}
                    </div>

                    <div class="mt-1 text-[10px] font-bold opacity-75 sm:text-[11px]">
                        {{ $card['active'] }} {{ __('team.active') }}
                    </div>

                    <div class="mt-2 rounded-full px-2.5 py-1 text-center text-[9px] font-black uppercase {{ $card['class'] === 'bg-[#003b7a] text-white' ? 'bg-[#ffd900] text-[#003b7a]' : 'bg-[#003b7a] text-white' }}">
                        @if ($card['rate'] !== null)
                            {{ number_format($card['rate'], 2) }}% {{ __('team.commission') }}
                        @else
                            {{ __('team.unlimited_tree') }}
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-4 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mt-5 sm:rounded-[2rem] sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('team.downline_list') }}
                    </h2>

                    <p class="mt-1 line-clamp-1 text-xs font-bold text-[#003b7a]/60 sm:line-clamp-none sm:text-sm">
                        {{ __('team.downline_list_desc') }}
                    </p>
                </div>

                <form method="GET" action="{{ route('team.index') }}" class="grid grid-cols-2 gap-2 sm:flex">
                    <select
                        name="level"
                        class="rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm"
                    >
                        <option value="all" @selected($level === 'all')>{{ __('team.all_levels') }}</option>
                        <option value="1" @selected((string) $level === '1')>{{ __('team.b_level') }}</option>
                        <option value="2" @selected((string) $level === '2')>{{ __('team.c_level') }}</option>
                        <option value="3" @selected((string) $level === '3')>{{ __('team.d_level') }}</option>
                    </select>

                    <select
                        name="status"
                        class="rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm"
                    >
                        <option value="all" @selected($status === 'all')>{{ __('team.all_status') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('status.active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('status.inactive') }}</option>
                        <option value="suspended" @selected($status === 'suspended')>{{ __('status.suspended') }}</option>
                    </select>

                    <button
                        type="submit"
                        class="col-span-2 rounded-2xl bg-[#003b7a] px-5 py-2 text-xs font-black uppercase text-white sm:col-span-1 sm:text-sm"
                    >
                        {{ __('team.filter') }}
                    </button>
                </form>
            </div>

            @if ($downlineUsers->isNotEmpty())
                <div class="mt-4 divide-y divide-[#003b7a]/10 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10 lg:hidden">
                    @foreach ($downlineUsers as $downline)
                        <div class="bg-[#fff8c7] p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0 flex items-center gap-2">
                                    <span class="shrink-0 rounded-full bg-[#003b7a] px-2.5 py-1 text-[10px] font-black uppercase text-white">
                                        L{{ $downline->team_depth }}
                                    </span>

                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-black text-[#003b7a]">
                                            {{ $downline->username }}
                                        </div>

                                        <div class="truncate text-[11px] font-bold text-[#003b7a]/55">
                                            {{ $downline->name ?: '-' }}
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase
                                    @class([
                                        'bg-green-100 text-green-700' => $downline->status === 'active',
                                        'bg-red-100 text-red-700' => $downline->status === 'suspended',
                                        'bg-gray-100 text-gray-600' => !in_array($downline->status, ['active', 'suspended'], true),
                                    ])"
                                >
                                    {{ __('status.' . $downline->status) }}
                                </span>
                            </div>

                            <div class="mt-2 grid grid-cols-3 gap-2 text-[10px] font-bold text-[#003b7a]/65">
                                <div class="min-w-0">
                                    <div class="font-black uppercase text-[#003b7a]/45">
                                        {{ __('team.phone') }}
                                    </div>

                                    <div class="truncate text-[#003b7a]">
                                        {{ $downline->phone ?: '-' }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <div class="font-black uppercase text-[#003b7a]/45">
                                        {{ __('team.referred_by') }}
                                    </div>

                                    <div class="truncate text-[#003b7a]">
                                        {{ $downline->referrer?->username ?: '-' }}
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <div class="font-black uppercase text-[#003b7a]/45">
                                        {{ __('team.joined_at') }}
                                    </div>

                                    <div class="truncate text-[#003b7a]">
                                        {{ $downline->created_at->format('d M Y') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 hidden overflow-x-auto rounded-[1.25rem] border border-[#003b7a]/10 lg:block">
                    <table class="w-full min-w-[900px] divide-y divide-[#003b7a]/10 text-sm">
                        <thead class="bg-[#fff8c7]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('team.level') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('auth.username') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('auth.full_name') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('team.phone') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('team.referred_by') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('app.account_status') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-black uppercase text-[#003b7a]/70">
                                    {{ __('team.joined_at') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#003b7a]/10 bg-white">
                            @foreach ($downlineUsers as $downline)
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-[#003b7a] px-3 py-1 text-xs font-black uppercase text-white">
                                            L{{ $downline->team_depth }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 font-black text-[#003b7a]">
                                        {{ $downline->username }}
                                    </td>

                                    <td class="px-4 py-3 font-bold text-[#003b7a]">
                                        {{ $downline->name }}
                                    </td>

                                    <td class="px-4 py-3 font-bold text-[#003b7a]/70">
                                        {{ $downline->phone ?: '-' }}
                                    </td>

                                    <td class="px-4 py-3 font-bold text-[#003b7a]/70">
                                        {{ $downline->referrer?->username ?: '-' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-3 py-1 text-xs font-black uppercase
                                            @class([
                                                'bg-green-100 text-green-700' => $downline->status === 'active',
                                                'bg-red-100 text-red-700' => $downline->status === 'suspended',
                                                'bg-gray-100 text-gray-600' => !in_array($downline->status, ['active', 'suspended'], true),
                                            ])"
                                        >
                                            {{ __('status.' . $downline->status) }}
                                        </span>
                                    </td>

                                    <td class="px-4 py-3 font-bold text-[#003b7a]/70">
                                        {{ $downline->created_at->format('d M Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $downlineUsers->links() }}
                </div>
            @else
                <div class="mt-4 rounded-[1.25rem] bg-[#fff8c7] p-6 text-center sm:p-8">
                    <div class="text-xl font-black text-[#003b7a] sm:text-2xl">
                        {{ __('team.no_downline') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('team.no_downline_desc') }}
                    </p>

                    <button
                        type="button"
                        class="mt-5 rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white"
                        onclick="navigator.clipboard.writeText('{{ $inviteUrl }}')"
                    >
                        {{ __('team.copy_invite_link') }}
                    </button>
                </div>
            @endif
        </div>
    </section>
@endsection