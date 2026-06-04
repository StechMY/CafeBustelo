@extends('layouts.app')

@section('title', __('salary.salary'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div>
                    <div
                         class="mb-3 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('salary.salary_center') }}
                    </div>

                    <h1
                        class="text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('salary.hero_title') }}
                    </h1>

                    <p class="mt-3 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:text-base">
                        {{ __('salary.hero_description') }}
                    </p>
                </div>

                <div
                     class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-4 shadow-[7px_7px_0_#003b7a] sm:rounded-[2rem] sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-xs font-black uppercase text-[#e4003a] sm:text-sm">
                        {{ __('salary.current_level') }}
                    </div>

                    <div class="mt-2 flex items-end gap-3">
                        <div class="text-5xl font-black leading-none text-[#003b7a]">
                            LV{{ $currentVipLevel?->level ?? 1 }}
                        </div>

                        @if ($currentVipLevel?->position_name)
                            <div class="pb-1 text-sm font-black uppercase text-[#e4003a]">
                                {{ $currentVipLevel->position_name }}
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-2xl bg-[#fff8c7] p-3">
                            <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                {{ __('salary.active_team_size') }}
                            </div>
                            <div class="mt-1 text-xl font-black text-[#003b7a]">
                                {{ $activeTeamSize }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#fff8c7] p-3">
                            <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                {{ __('dashboard.accumulated_recharge') }}
                            </div>
                            <div class="mt-1 text-xl font-black text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $user->accumulated_recharge, 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid gap-3 md:grid-cols-3">
            @foreach (['weekly', 'monthly', 'annual'] as $type)
                @php
                    $state = $claimStates[$type];

                    $salaryAmount = $currentVipLevel?->salaryAmountForType($type) ?? 0;
                    $teamSubsidyAmount = $currentVipLevel?->teamSubsidyForType($type) ?? 0;
                    $totalAmount = $salaryAmount + $teamSubsidyAmount;

                    $canClaim =
                        $currentVipLevel &&
                        $state['enabled'] &&
                        $state['within_window'] &&
                        !$state['claimed'] &&
                        $totalAmount > 0;
                @endphp

                <article class="rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-black uppercase text-[#e4003a]">
                                {{ __('salary.types.' . $type) }}
                            </div>

                            <div class="mt-2 text-3xl font-black text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $totalAmount, 2) }}
                            </div>
                        </div>

                        <span
                              class="rounded-full px-3 py-1 text-[10px] font-black uppercase
                            @class([
                                'bg-green-100 text-green-700' => $canClaim,
                                'bg-yellow-100 text-yellow-700' => $state['claimed'],
                                'bg-red-100 text-red-700' => !$state['within_window'] && !$state['claimed'],
                                'bg-gray-100 text-gray-600' =>
                                    !$canClaim && !$state['claimed'] && $state['within_window'],
                            ])">
                            @if ($state['claimed'])
                                {{ __('salary.claimed') }}
                            @elseif ($canClaim)
                                {{ __('salary.available') }}
                            @elseif (!$state['within_window'])
                                {{ __('salary.window_closed') }}
                            @else
                                {{ __('salary.unavailable') }}
                            @endif
                        </span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-2xl bg-[#fff8c7] p-3">
                            <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                {{ __('salary.salary_amount') }}
                            </div>

                            <div class="mt-1 text-sm font-black text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $salaryAmount, 2) }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-[#fff8c7] p-3">
                            <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                {{ __('salary.team_subsidy') }}
                            </div>

                            <div class="mt-1 text-sm font-black text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $teamSubsidyAmount, 2) }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 rounded-2xl bg-[#fff8c7] p-3 text-xs font-bold text-[#003b7a]/70">
                        {{ __('salary.period') }}: {{ $state['period_key'] }}
                    </div>

                    <div class="mt-2 rounded-2xl bg-white p-3 text-xs font-bold text-[#003b7a]/70 ring-1 ring-[#003b7a]/10">
                        {{ __('salary.claim_window') }}: {{ $state['claim_window'] }}
                    </div>

                    @if ($canClaim)
                        <form method="POST" action="{{ route('salary.claim', $type) }}" class="mt-4">
                            @csrf

                            <button
                                    type="submit"
                                    class="w-full rounded-2xl bg-[#e4003a] px-4 py-3 text-sm font-black uppercase text-white shadow-lg">
                                {{ __('salary.claim_now') }}
                            </button>
                        </form>
                    @else
                        <button
                                type="button"
                                disabled
                                class="mt-4 w-full cursor-not-allowed rounded-2xl bg-gray-200 px-4 py-3 text-sm font-black uppercase text-gray-500">
                            @if ($state['claimed'])
                                {{ __('salary.already_claimed') }}
                            @elseif (!$state['within_window'])
                                {{ __('salary.claim_window_closed_short') }}
                            @else
                                {{ __('salary.cannot_claim') }}
                            @endif
                        </button>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="mt-5 overflow-x-auto rounded-[1.25rem] border border-[#003b7a]/10 ">
            <table class="w-full min-w-[1100px] divide-y divide-[#003b7a]/10 text-sm">
                <thead class="bg-[#fff8c7]">
                    <tr>
                        <th class="px-4 py-3 text-left font-black uppercase text-[#003b7a]/70">Level</th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">
                            {{ __('salary.required_recharge') }}</th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">
                            {{ __('salary.team_size') }}</th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">
                            {{ __('salary.daily_check_in') }}</th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">{{ __('salary.weekly') }}
                        </th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">{{ __('salary.monthly') }}
                        </th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">
                            {{ __('salary.team_subsidy') }}</th>
                        <th class="px-4 py-3 text-right font-black uppercase text-[#003b7a]/70">{{ __('salary.annual') }}
                        </th>
                        <th class="px-4 py-3 text-left font-black uppercase text-[#003b7a]/70">{{ __('salary.position') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-[#003b7a]/10 bg-white">
                    @foreach ($vipLevels as $level)
                        <tr @class([
                            'bg-green-50' => $currentVipLevel && $currentVipLevel->id === $level->id,
                        ])>
                            <td class="px-4 py-3 font-black text-[#003b7a]">
                                LV{{ $level->level }}
                                <div class="text-xs font-bold text-[#003b7a]/60">
                                    {{ $level->name }}
                                </div>
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $level->required_accumulated_recharge, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ $level->required_active_team_size }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $level->daily_check_in_amount, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $level->weekly_salary, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $level->monthly_salary, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $level->team_subsidy, 2) }}
                            </td>

                            <td class="px-4 py-3 text-right font-bold text-[#003b7a]">
                                {{ config('app.currency') }} {{ number_format((float) $level->annual_salary, 2) }}
                            </td>

                            <td class="px-4 py-3 font-bold text-[#003b7a]/70">
                                {{ $level->position_name ?: '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-5 rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
            <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                {{ __('salary.claim_history') }}
            </h2>

            @if ($claims->isNotEmpty())
                <div class="mt-4 space-y-3">
                    @foreach ($claims as $claim)
                        <div class="rounded-[1.25rem] bg-[#fff8c7] p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-base font-black text-[#003b7a]">
                                        {{ __('salary.types.' . $claim->type) }}
                                        · LV{{ $claim->vip_level_snapshot }}
                                    </div>

                                    <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                        {{ $claim->claimed_at->format('d M Y, h:i A') }}
                                    </div>

                                    <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                        {{ __('salary.period') }}: {{ $claim->period_key }}
                                    </div>
                                </div>

                                <div class="text-right text-base font-black text-green-600">
                                    + {{ config('app.currency') }} {{ number_format((float) $claim->total_amount, 2) }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5">
                    {{ $claims->links() }}
                </div>
            @else
                <div class="mt-4 rounded-[1.25rem] bg-[#fff8c7] p-8 text-center">
                    <div class="text-xl font-black text-[#003b7a]">
                        {{ __('salary.no_claims') }}
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
