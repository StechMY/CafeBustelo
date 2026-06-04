@extends('layouts.app')

@section('title', __('team.team'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div>
                    <div
                         class="mb-3 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('team.team_center') }}
                    </div>

                    <h1
                        class="text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('team.hero_title') }}
                    </h1>

                    <p class="mt-3 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:text-base">
                        {{ __('team.hero_description') }}
                    </p>
                </div>

                <div
                     x-data="{
                         copied: false,
                         copy() {
                             navigator.clipboard.writeText('{{ url('/register?ref=' . $user->referral_code) }}');
                             this.copied = true;
                             setTimeout(() => this.copied = false, 1800);
                         }
                     }"
                     class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-4 shadow-[7px_7px_0_#003b7a] sm:rounded-[2rem] sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-xs font-black uppercase text-[#e4003a] sm:text-sm">
                        {{ __('team.your_referral_code') }}
                    </div>

                    <div class="mt-2 break-all text-3xl font-black text-[#003b7a] sm:text-4xl">
                        {{ $user->referral_code }}
                    </div>

                    <div class="mt-3 rounded-2xl bg-[#fff8c7] p-3 text-xs font-bold text-[#003b7a]/70">
                        {{ url('/register?ref=' . $user->referral_code) }}
                    </div>

                    <button
                            type="button"
                            class="mt-4 w-full rounded-2xl bg-[#e4003a] px-5 py-3 text-sm font-black uppercase text-white shadow-lg"
                            @click="copy()"
                            x-text="copied ? '{{ __('team.copied') }}' : '{{ __('team.copy_invite_link') }}'"></button>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
        @php
            $bCommissionRate = (float) data_get($commissionRates, '1.rate_percent', 0);
            $cCommissionRate = (float) data_get($commissionRates, '2.rate_percent', 0);
            $dCommissionRate = (float) data_get($commissionRates, '3.rate_percent', 0);
        @endphp

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <a href="{{ route('team.index', ['level' => 1, 'status' => $status]) }}"
               class="rounded-[1.35rem] bg-white p-4 shadow-lg ring-1 ring-black/5 transition hover:-translate-y-0.5 {{ in_array($level, ['1', 'b', 'B'], true) ? 'outline outline-4 outline-[#ffd900]' : '' }}">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                    {{ __('team.b_level') }}
                </div>

                <div class="mt-2 text-3xl font-black text-[#003b7a]">
                    {{ $stats['b_total'] }}
                </div>

                <div class="mt-1 text-[11px] font-bold text-green-600">
                    {{ $stats['b_active'] }} {{ __('team.active') }}
                </div>

                <div
                     class="mt-3 rounded-full bg-[#003b7a] px-3 py-1 text-center text-[10px] font-black uppercase text-white">
                    {{ number_format($bCommissionRate, 2) }}% {{ __('team.commission') }}
                </div>
            </a>

            <a href="{{ route('team.index', ['level' => 2, 'status' => $status]) }}"
               class="rounded-[1.35rem] bg-white p-4 shadow-lg ring-1 ring-black/5 transition hover:-translate-y-0.5 {{ in_array($level, ['2', 'c', 'C'], true) ? 'outline outline-4 outline-[#ffd900]' : '' }}">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                    {{ __('team.c_level') }}
                </div>

                <div class="mt-2 text-3xl font-black text-[#003b7a]">
                    {{ $stats['c_total'] }}
                </div>

                <div class="mt-1 text-[11px] font-bold text-green-600">
                    {{ $stats['c_active'] }} {{ __('team.active') }}
                </div>

                <div
                     class="mt-3 rounded-full bg-[#003b7a] px-3 py-1 text-center text-[10px] font-black uppercase text-white">
                    {{ number_format($cCommissionRate, 2) }}% {{ __('team.commission') }}
                </div>
            </a>

            <a href="{{ route('team.index', ['level' => 3, 'status' => $status]) }}"
               class="rounded-[1.35rem] bg-white p-4 shadow-lg ring-1 ring-black/5 transition hover:-translate-y-0.5 {{ in_array($level, ['3', 'd', 'D'], true) ? 'outline outline-4 outline-[#ffd900]' : '' }}">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                    {{ __('team.d_level') }}
                </div>

                <div class="mt-2 text-3xl font-black text-[#003b7a]">
                    {{ $stats['d_total'] }}
                </div>

                <div class="mt-1 text-[11px] font-bold text-green-600">
                    {{ $stats['d_active'] }} {{ __('team.active') }}
                </div>

                <div
                     class="mt-3 rounded-full bg-[#003b7a] px-3 py-1 text-center text-[10px] font-black uppercase text-white">
                    {{ number_format($dCommissionRate, 2) }}% {{ __('team.commission') }}
                </div>
            </a>

            <a href="{{ route('team.index', ['level' => 'all', 'status' => $status]) }}"
               class="rounded-[1.35rem] bg-[#003b7a] p-4 text-white shadow-lg transition hover:-translate-y-0.5 {{ $level === 'all' ? 'outline outline-4 outline-[#ffd900]' : '' }}">
                <div class="text-[10px] font-black uppercase text-white/60">
                    {{ __('team.all_downline') }}
                </div>

                <div class="mt-2 text-3xl font-black">
                    {{ $stats['all_total'] }}
                </div>

                <div class="mt-1 text-[11px] font-bold text-white/80">
                    {{ $stats['all_active'] }} {{ __('team.active') }}
                </div>

                <div
                     class="mt-3 rounded-full bg-[#ffd900] px-3 py-1 text-center text-[10px] font-black uppercase text-[#003b7a]">
                    {{ __('team.unlimited_tree') }}
                </div>
            </a>
        </div>

        <div class="mt-5 rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('team.downline_list') }}
                    </h2>

                    <p class="mt-1 text-xs font-bold text-[#003b7a]/60 sm:text-sm">
                        {{ __('team.downline_list_desc') }}
                    </p>
                </div>

                <form method="GET" action="{{ route('team.index') }}" class="grid grid-cols-2 gap-2 sm:flex">
                    <select
                            name="level"
                            class="rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm">
                        <option value="all" @selected($level === 'all')>{{ __('team.all_levels') }}</option>
                        <option value="1" @selected((string) $level === '1')>{{ __('team.b_level') }}</option>
                        <option value="2" @selected((string) $level === '2')>{{ __('team.c_level') }}</option>
                        <option value="3" @selected((string) $level === '3')>{{ __('team.d_level') }}</option>
                    </select>

                    <select
                            name="status"
                            class="rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm">
                        <option value="all" @selected($status === 'all')>{{ __('team.all_status') }}</option>
                        <option value="active" @selected($status === 'active')>{{ __('status.active') }}</option>
                        <option value="inactive" @selected($status === 'inactive')>{{ __('status.inactive') }}</option>
                        <option value="suspended" @selected($status === 'suspended')>{{ __('status.suspended') }}</option>
                    </select>

                    <button
                            type="submit"
                            class="col-span-2 rounded-2xl bg-[#003b7a] px-5 py-2 text-xs font-black uppercase text-white sm:col-span-1 sm:text-sm">
                        {{ __('team.filter') }}
                    </button>
                </form>
            </div>

            @if ($downlineUsers->isNotEmpty())
                <div class="mt-5 space-y-3 lg:hidden">
                    @foreach ($downlineUsers as $downline)
                        <div class="rounded-[1.25rem] bg-[#fff8c7] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span
                                              class="rounded-full bg-[#003b7a] px-2.5 py-1 text-[10px] font-black uppercase text-white">
                                            L{{ $downline->team_depth }}
                                        </span>

                                        <div class="truncate text-base font-black text-[#003b7a]">
                                            {{ $downline->username }}
                                        </div>
                                    </div>

                                    <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                        {{ $downline->name }}
                                    </div>
                                </div>

                                <span
                                      class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase
                                    @class([
                                        'bg-green-100 text-green-700' => $downline->status === 'active',
                                        'bg-red-100 text-red-700' => $downline->status === 'suspended',
                                        'bg-gray-100 text-gray-600' => !in_array(
                                            $downline->status,
                                            ['active', 'suspended'],
                                            true),
                                    ])">
                                    {{ __('status.' . $downline->status) }}
                                </span>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('team.phone') }}
                                    </div>

                                    <div class="mt-1 break-all text-xs font-black text-[#003b7a]">
                                        {{ $downline->phone ?: '-' }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('team.referred_by') }}
                                    </div>

                                    <div class="mt-1 truncate text-xs font-black text-[#003b7a]">
                                        {{ $downline->referrer?->username ?: '-' }}
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 text-[11px] font-bold text-[#003b7a]/60">
                                {{ __('team.joined_at') }}:
                                {{ $downline->created_at->format('d M Y, h:i A') }}
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
                                        <span
                                              class="rounded-full bg-[#003b7a] px-3 py-1 text-xs font-black uppercase text-white">
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
                                                'bg-gray-100 text-gray-600' => !in_array(
                                                    $downline->status,
                                                    ['active', 'suspended'],
                                                    true),
                                            ])">
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

                <div class="mt-5">
                    {{ $downlineUsers->links() }}
                </div>
            @else
                <div class="mt-5 rounded-[1.25rem] bg-[#fff8c7] p-8 text-center">
                    <div class="text-2xl font-black text-[#003b7a]">
                        {{ __('team.no_downline') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('team.no_downline_desc') }}
                    </p>

                    <button
                            type="button"
                            class="mt-5 rounded-full bg-[#e4003a] px-6 py-3 text-sm font-black uppercase text-white"
                            onclick="navigator.clipboard.writeText('{{ url('/register?ref=' . $user->referral_code) }}')">
                        {{ __('team.copy_invite_link') }}
                    </button>
                </div>
            @endif
        </div>
    </section>
@endsection
