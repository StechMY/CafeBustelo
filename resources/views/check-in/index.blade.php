@extends('layouts.app')

@section('title', __('checkin.check_in'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
        $todayAmount = $currentVipLevel?->daily_check_in_amount ?? 0;
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-5 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#003b7a] shadow">
                        {{ __('checkin.daily_reward') }}
                    </div>

                    <h1
                        class="mt-4 max-w-3xl text-3xl font-black sm:text-4xl uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('checkin.check_in') }}
                    </h1>

                    <p class="mt-5 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/75 sm:text-base">
                        {{ __('checkin.hero_desc') }}
                    </p>
                </div>

                <div class="rounded-[2rem] border-4 border-[#003b7a] bg-white p-4 shadow-[10px_10px_0_#003b7a] sm:p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-xs font-black uppercase tracking-[0.18em] text-[#e4003a]">
                                {{ __('checkin.today_reward') }}
                            </div>

                            <div class="mt-2 break-words text-3xl font-black leading-none text-[#003b7a] sm:text-5xl">
                                {{ $currency }} {{ number_format((float) $todayAmount, 2) }}
                            </div>

                            <div
                                 class="mt-3 inline-flex rounded-full bg-[#ffd900] px-4 py-2 text-xs font-black uppercase text-[#003b7a]">
                                LV{{ $currentVipLevel?->level ?? 1 }}
                            </div>
                        </div>

                        <div
                             class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-[#003b7a] text-white shadow-lg sm:h-14 sm:w-14">
                            @include('partials.icon', [
                                'name' => 'check-in',
                                'class' => 'h-6 w-6 sm:h-7 sm:w-7',
                            ])
                        </div>
                    </div>

                    @if ($hasClaimedToday)
                        <button
                                type="button"
                                disabled
                                class="mt-6 w-full cursor-not-allowed rounded-2xl bg-gray-100 px-5 py-4 text-sm font-black uppercase text-[#003b7a]/45">
                            {{ __('checkin.claimed_today') }}
                        </button>

                        @if ($todayRecord)
                            <div class="mt-3 rounded-2xl bg-[#003b7a] p-4 text-sm font-bold text-white/80">
                                {{ __('checkin.claimed_at') }}:
                                <span class="text-[#ffd900]">
                                    {{ $todayRecord->claimed_at->format('d M Y, h:i A') }}
                                </span>
                            </div>
                        @endif
                    @else
                        <form method="POST" action="{{ route('check-in.claim') }}" class="mt-6">
                            @csrf

                            <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-[#e4003a] px-5 py-4 text-sm font-black uppercase text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                                {{ __('checkin.claim_now') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f5f5f5]">
        <div class="mx-auto max-w-7xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-4">
                <div class="rounded-[2rem] bg-[#003b7a] p-5 text-white shadow-xl">
                    <div class="text-xs font-black uppercase text-white/65">
                        {{ __('checkin.today_reward') }}
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl">
                        {{ $currency }} {{ number_format((float) $todayAmount, 2) }}
                    </div>

                    <p class="mt-3 text-sm font-bold text-white/65">
                        {{ __('checkin.per_day') }} · LV{{ $currentVipLevel?->level ?? 1 }}
                    </p>
                </div>

                <div class="rounded-[2rem] bg-[#e4003a] p-5 text-white shadow-xl">
                    <div class="text-xs font-black uppercase text-white/65">
                        {{ __('checkin.this_month') }}
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl">
                        {{ $currency }} {{ number_format((float) $thisMonthClaimed, 2) }}
                    </div>

                    <p class="mt-3 text-sm font-bold text-white/65">
                        {{ __('checkin.summary') }}
                    </p>
                </div>

                <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('checkin.total_claimed') }}
                    </div>

                    <div class="mt-4 break-words text-3xl font-black sm:text-4xl text-[#003b7a]">
                        {{ $currency }} {{ number_format((float) $totalClaimed, 2) }}
                    </div>
                </div>

                <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('profile.current_vip') }}
                    </div>

                    <div class="mt-3 text-3xl font-black text-[#003b7a] sm:text-4xl">
                        LV{{ $currentVipLevel?->level ?? 1 }}
                    </div>

                    <p class="mt-3 text-sm font-bold text-[#003b7a]/55">
                        {{ $currentVipLevel?->name }}
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-6 xl:grid-cols-[420px_1fr]">
                <aside class="space-y-6">
                    <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                        <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                            {{ __('checkin.vip_reward_table') }}
                        </h2>

                        <div class="mt-5 space-y-3">
                            @foreach ($vipLevels as $level)
                                <div
                                     class="flex items-center justify-between gap-3 rounded-[1.35rem] p-4 ring-1 ring-[#003b7a]/10
                                    {{ $currentVipLevel && $currentVipLevel->id === $level->id ? 'bg-[#ffd900]' : 'bg-[#fff8c7]' }}">
                                    <div>
                                        <div class="text-lg font-black text-[#003b7a]">
                                            LV{{ $level->level }}
                                        </div>

                                        <div class="text-xs font-bold text-[#003b7a]/60">
                                            {{ $level->name }}
                                        </div>
                                    </div>

                                    <div class="text-right">
                                        <div class="text-base font-black text-[#003b7a]">
                                            {{ $currency }}
                                            {{ number_format((float) $level->daily_check_in_amount, 2) }}
                                        </div>

                                        <div class="text-[10px] font-black uppercase text-[#003b7a]/50">
                                            {{ __('checkin.per_day') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </aside>

                <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                                {{ __('checkin.history') }}
                            </h2>

                            <p class="mt-1 text-sm font-bold text-[#003b7a]/55">
                                {{ __('checkin.no_records_desc') }}
                            </p>
                        </div>

                        <a
                           href="{{ route('wallet.index') }}"
                           class="inline-flex justify-center rounded-full bg-[#003b7a] px-5 py-2.5 text-xs font-black uppercase text-white">
                            {{ __('navigation.wallet') }}
                        </a>
                    </div>

                    @if ($records->isNotEmpty())
                        <div class="mt-5 space-y-3">
                            @foreach ($records as $record)
                                <div
                                     class="flex items-center justify-between gap-4 rounded-[1.35rem] bg-[#fff8c7] p-4 ring-1 ring-[#003b7a]/10">
                                    <div class="flex items-center gap-3">
                                        <div class="grid h-11 w-11 place-items-center rounded-2xl bg-[#003b7a] text-white">
                                            @include('partials.icon', [
                                                'name' => 'check-in',
                                                'class' => 'h-5 w-5',
                                            ])
                                        </div>

                                        <div>
                                            <div class="font-black text-[#003b7a]">
                                                {{ $record->claim_date->format('d M Y') }}
                                            </div>

                                            <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                                LV{{ $record->vip_level_snapshot }}
                                                · {{ $record->claimed_at->format('h:i A') }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-right text-lg font-black text-green-600">
                                        + {{ $currency }} {{ number_format((float) $record->amount, 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5">
                            {{ $records->links() }}
                        </div>
                    @else
                        <div class="mt-5 rounded-[1.5rem] bg-[#fff8c7] p-10 text-center ring-1 ring-[#003b7a]/10">
                            <div class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#003b7a] text-white">
                                @include('partials.icon', ['name' => 'check-in', 'class' => 'h-8 w-8'])
                            </div>

                            <div class="mt-4 text-xl font-black text-[#003b7a] sm:text-2xl">
                                {{ __('checkin.no_records') }}
                            </div>

                            <p class="mt-2 text-sm font-bold text-[#003b7a]/60">
                                {{ __('checkin.no_records_desc') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
