@extends('layouts.app')

@section('title', __('salary.salary'))

@section('content')
    @php
        $currency = config('app.currency');

        $salaryTypes = ['weekly', 'monthly', 'annual'];
    @endphp

    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-3 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div>
                    <div class="mb-2 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('salary.salary_center') }}
                    </div>

                    <h1 class="text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('salary.hero_title') }}
                    </h1>

                    <p class="mt-2 line-clamp-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:line-clamp-none sm:text-base">
                        {{ __('salary.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-3 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-sm">
                                {{ __('salary.current_level') }}
                            </div>

                            <div class="mt-1 flex items-end gap-2 sm:mt-2 sm:gap-3">
                                <div class="text-4xl font-black leading-none text-[#003b7a] sm:text-5xl">
                                    LV{{ $currentVipLevel?->level ?? 1 }}
                                </div>

                                @if ($currentVipLevel?->position_name)
                                    <div class="pb-1 text-xs font-black uppercase text-[#e4003a] sm:text-sm">
                                        {{ $currentVipLevel->position_name }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:mt-4">
                        <div class="rounded-xl bg-[#fff8c7] p-3 sm:rounded-2xl">
                            <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-[10px]">
                                {{ __('salary.active_team_size') }}
                            </div>

                            <div class="mt-1 text-xl font-black text-[#003b7a]">
                                {{ $activeTeamSize }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-[#fff8c7] p-3 sm:rounded-2xl">
                            <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-[10px]">
                                {{ __('dashboard.accumulated_recharge') }}
                            </div>

                            <div class="mt-1 break-words text-lg font-black text-[#003b7a] sm:text-xl">
                                {{ $currency }} {{ number_format((float) $user->accumulated_recharge, 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid gap-3 md:grid-cols-3">
            @foreach ($salaryTypes as $type)
                @php
                    $state = $claimStates[$type];

                    $salaryAmount = $currentVipLevel?->salaryAmountForType($type) ?? 0;
                    $teamSubsidyAmount = $currentVipLevel?->teamSubsidyForType($type) ?? 0;
                    $totalAmount = $salaryAmount + $teamSubsidyAmount;

                    $canClaim =
                        $currentVipLevel &&
                        $state['enabled'] &&
                        $state['within_window'] &&
                        ! $state['claimed'] &&
                        $totalAmount > 0;
                @endphp

                <article class="rounded-[1.25rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[1.5rem] sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[10px] font-black uppercase text-[#e4003a] sm:text-xs">
                                {{ __('salary.types.' . $type) }}
                            </div>

                            <div class="mt-1 break-words text-2xl font-black text-[#003b7a] sm:mt-2 sm:text-3xl">
                                {{ $currency }} {{ number_format((float) $totalAmount, 2) }}
                            </div>
                        </div>

                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black uppercase sm:px-3 sm:text-[10px]
                            @class([
                                'bg-green-100 text-green-700' => $canClaim,
                                'bg-yellow-100 text-yellow-700' => $state['claimed'],
                                'bg-red-100 text-red-700' => ! $state['within_window'] && ! $state['claimed'],
                                'bg-gray-100 text-gray-600' => ! $canClaim && ! $state['claimed'] && $state['within_window'],
                            ])"
                        >
                            @if ($state['claimed'])
                                {{ __('salary.claimed') }}
                            @elseif ($canClaim)
                                {{ __('salary.available') }}
                            @elseif (! $state['within_window'])
                                {{ __('salary.window_closed') }}
                            @else
                                {{ __('salary.unavailable') }}
                            @endif
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:mt-4">
                        <div class="rounded-xl bg-[#fff8c7] p-3 sm:rounded-2xl">
                            <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-[10px]">
                                {{ __('salary.salary_amount') }}
                            </div>

                            <div class="mt-1 text-sm font-black text-[#003b7a]">
                                {{ $currency }} {{ number_format((float) $salaryAmount, 2) }}
                            </div>
                        </div>

                        <div class="rounded-xl bg-[#fff8c7] p-3 sm:rounded-2xl">
                            <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-[10px]">
                                {{ __('salary.team_subsidy') }}
                            </div>

                            <div class="mt-1 text-sm font-black text-[#003b7a]">
                                {{ $currency }} {{ number_format((float) $teamSubsidyAmount, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-2 grid gap-2 text-[10px] font-bold text-[#003b7a]/70 sm:text-xs">
                        <div class="rounded-xl bg-[#fff8c7] p-2.5 sm:rounded-2xl sm:p-3">
                            {{ __('salary.period') }}: {{ $state['period_key'] }}
                        </div>

                        <div class="rounded-xl bg-white p-2.5 ring-1 ring-[#003b7a]/10 sm:rounded-2xl sm:p-3">
                            {{ __('salary.claim_window') }}: {{ $state['claim_window'] }}
                        </div>
                    </div>

                    @if ($canClaim)
                        <form method="POST" action="{{ route('salary.claim', $type) }}" class="mt-3 sm:mt-4">
                            @csrf

                            <button
                                type="submit"
                                class="w-full rounded-xl bg-[#e4003a] px-4 py-2.5 text-xs font-black uppercase text-white shadow-lg sm:rounded-2xl sm:py-3 sm:text-sm"
                            >
                                {{ __('salary.claim_now') }}
                            </button>
                        </form>
                    @else
                        <button
                            type="button"
                            disabled
                            class="mt-3 w-full cursor-not-allowed rounded-xl bg-gray-200 px-4 py-2.5 text-xs font-black uppercase text-gray-500 sm:mt-4 sm:rounded-2xl sm:py-3 sm:text-sm"
                        >
                            @if ($state['claimed'])
                                {{ __('salary.already_claimed') }}
                            @elseif (! $state['within_window'])
                                {{ __('salary.claim_window_closed_short') }}
                            @else
                                {{ __('salary.cannot_claim') }}
                            @endif
                        </button>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-4 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mt-5 sm:rounded-[2rem] sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('salary.salary') }}
                    </h2>

                    <p class="mt-1 text-xs font-bold text-[#003b7a]/55 sm:text-sm">
                        {{ __('salary.current_level') }} / {{ __('salary.required_recharge') }}
                    </p>
                </div>
            </div>

            {{-- Mobile level cards --}}
            <div class="-mx-4 mt-4 flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 [-webkit-overflow-scrolling:touch] lg:hidden">
                @foreach ($vipLevels as $level)
                    @php
                        $isCurrent = $currentVipLevel && $currentVipLevel->id === $level->id;
                    @endphp

                    <div
                        class="w-[72vw] shrink-0 snap-start rounded-[1.25rem] p-4 ring-1 ring-black/5 {{ $isCurrent ? 'bg-[#003b7a] text-white' : 'bg-[#fff8c7] text-[#003b7a]' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-3xl font-black leading-none">
                                    LV{{ $level->level }}
                                </div>

                                <div class="mt-1 text-xs font-bold opacity-70">
                                    {{ $level->name }}
                                </div>
                            </div>

                            @if ($isCurrent)
                                <div class="rounded-full bg-[#ffd900] px-3 py-1 text-[10px] font-black uppercase text-[#003b7a]">
                                    {{ __('salary.current_level') }}
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-white/70 p-2.5 text-[#003b7a]">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('salary.required_recharge') }}
                                </div>

                                <div class="mt-1 text-xs font-black">
                                    {{ $currency }} {{ number_format((float) $level->required_accumulated_recharge, 0) }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 p-2.5 text-[#003b7a]">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('salary.team_size') }}
                                </div>

                                <div class="mt-1 text-xs font-black">
                                    {{ $level->required_active_team_size }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 p-2.5 text-[#003b7a]">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('salary.weekly') }}
                                </div>

                                <div class="mt-1 text-xs font-black">
                                    {{ $currency }} {{ number_format((float) $level->weekly_salary, 0) }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 p-2.5 text-[#003b7a]">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('salary.monthly') }}
                                </div>

                                <div class="mt-1 text-xs font-black">
                                    {{ $currency }} {{ number_format((float) $level->monthly_salary, 0) }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 p-2.5 text-[#003b7a]">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('salary.annual') }}
                                </div>

                                <div class="mt-1 text-xs font-black">
                                    {{ $currency }} {{ number_format((float) $level->annual_salary, 0) }}
                                </div>
                            </div>

                            <div class="rounded-xl bg-white/70 p-2.5 text-[#003b7a]">
                                <div class="text-[9px] font-black uppercase text-[#003b7a]/55">
                                    {{ __('salary.team_subsidy') }}
                                </div>

                                <div class="mt-1 text-xs font-black">
                                    {{ $currency }} {{ number_format((float) $level->team_subsidy, 0) }}
                                </div>
                            </div>
                        </div>

                        @if ($level->position_name)
                            <div class="mt-3 rounded-xl bg-white/70 p-2.5 text-xs font-black text-[#003b7a]">
                                {{ __('salary.position') }}: {{ $level->position_name }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Desktop table --}}
            <div class="mt-4 hidden overflow-x-auto rounded-[1.25rem] border border-[#003b7a]/10 lg:block">
                <table class="w-full min-w-[1050px] divide-y divide-[#003b7a]/10 text-sm">
                    <thead class="bg-[#fff8c7]">
                        <tr>
                            <th class="px-3 py-3 text-left font-black uppercase text-[#003b7a]/70">Level</th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.required_recharge') }}
                            </th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.team_size') }}
                            </th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.daily_check_in') }}
                            </th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.weekly') }}
                            </th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.monthly') }}
                            </th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.team_subsidy') }}
                            </th>
                            <th class="px-3 py-3 text-right font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.annual') }}
                            </th>
                            <th class="px-3 py-3 text-left font-black uppercase text-[#003b7a]/70">
                                {{ __('salary.position') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#003b7a]/10 bg-white">
                        @foreach ($vipLevels as $level)
                            <tr @class([
                                'bg-green-50' => $currentVipLevel && $currentVipLevel->id === $level->id,
                            ])>
                                <td class="px-3 py-3 font-black text-[#003b7a]">
                                    LV{{ $level->level }}
                                    <div class="text-xs font-bold text-[#003b7a]/60">
                                        {{ $level->name }}
                                    </div>
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $level->required_accumulated_recharge, 2) }}
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $level->required_active_team_size }}
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $level->daily_check_in_amount, 2) }}
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $level->weekly_salary, 2) }}
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $level->monthly_salary, 2) }}
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $level->team_subsidy, 2) }}
                                </td>

                                <td class="px-3 py-3 text-right font-bold text-[#003b7a]">
                                    {{ $currency }} {{ number_format((float) $level->annual_salary, 2) }}
                                </td>

                                <td class="px-3 py-3 font-bold text-[#003b7a]/70">
                                    {{ $level->position_name ?: '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 rounded-[1.35rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:mt-5 sm:rounded-[2rem] sm:p-6">
            <h2 class="text-lg font-black uppercase text-[#003b7a] sm:text-2xl">
                {{ __('salary.claim_history') }}
            </h2>

            @if ($claims->isNotEmpty())
                <div class="mt-4 divide-y divide-[#003b7a]/10 overflow-hidden rounded-[1.25rem] border border-[#003b7a]/10">
                    @foreach ($claims as $claim)
                        <div class="bg-[#fff8c7] p-3 sm:p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-black text-[#003b7a] sm:text-base">
                                        {{ __('salary.types.' . $claim->type) }}
                                        · LV{{ $claim->vip_level_snapshot }}
                                    </div>

                                    <div class="mt-0.5 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                                        {{ $claim->claimed_at->format('d M Y, h:i A') }}
                                    </div>

                                    <div class="mt-0.5 truncate text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                                        {{ __('salary.period') }}: {{ $claim->period_key }}
                                    </div>
                                </div>

                                <div class="shrink-0 text-right text-sm font-black text-green-600 sm:text-base">
                                    + {{ $currency }} {{ number_format((float) $claim->total_amount, 2) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 sm:mt-5">
                    {{ $claims->links() }}
                </div>
            @else
                <div class="mt-4 rounded-[1.25rem] bg-[#fff8c7] p-6 text-center sm:p-8">
                    <div class="text-lg font-black text-[#003b7a] sm:text-xl">
                        {{ __('salary.no_claims') }}
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection