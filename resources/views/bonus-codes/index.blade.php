@extends('layouts.app')

@section('title', __('bonus.bonus_codes'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
            <div class="grid gap-5 lg:grid-cols-[1.35fr_0.9fr] lg:items-center">
                <div>
                    <div class="mb-3 inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide shadow sm:px-4 sm:py-2 sm:text-xs">
                        {{ __('bonus.bonus_center') }}
                    </div>

                    <h1 class="text-4xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:text-6xl lg:text-7xl">
                        {{ __('bonus.hero_title') }}
                    </h1>

                    <p class="mt-3 max-w-2xl text-sm font-bold leading-relaxed text-[#003b7a]/80 sm:mt-5 sm:text-base">
                        {{ __('bonus.hero_description') }}
                    </p>
                </div>

                <div class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-4 shadow-[7px_7px_0_#003b7a] sm:rounded-[2rem] sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-xs font-black uppercase text-[#e4003a] sm:text-sm">
                        {{ __('bonus.available_amount') }}
                    </div>

                    <div class="mt-2 text-4xl font-black text-[#003b7a]">
                        {{ config('app.currency') }} {{ number_format((float) $stats['unused_amount'], 2) }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('bonus.available_amount_desc') }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-3 py-5 sm:px-6 sm:py-8 lg:px-8">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div class="rounded-[1.25rem] bg-[#003b7a] p-4 text-white shadow-lg sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-white/70 sm:text-sm">
                    {{ __('bonus.unused_codes') }}
                </div>

                <div class="mt-2 text-3xl font-black">
                    {{ $stats['unused_count'] }}
                </div>
            </div>

            <div class="rounded-[1.25rem] bg-white p-4 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-sm">
                    {{ __('bonus.unused_amount') }}
                </div>

                <div class="mt-2 break-words text-2xl font-black text-[#003b7a] sm:text-3xl">
                    {{ config('app.currency') }} {{ number_format((float) $stats['unused_amount'], 2) }}
                </div>
            </div>

            <div class="rounded-[1.25rem] bg-[#e4003a] p-4 text-white shadow-lg sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-white/70 sm:text-sm">
                    {{ __('bonus.used_codes') }}
                </div>

                <div class="mt-2 text-3xl font-black">
                    {{ $stats['used_count'] }}
                </div>
            </div>

            <div class="rounded-[1.25rem] bg-white p-4 shadow-lg ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
                <div class="text-[10px] font-black uppercase text-[#003b7a]/70 sm:text-sm">
                    {{ __('bonus.used_amount') }}
                </div>

                <div class="mt-2 break-words text-2xl font-black text-[#003b7a] sm:text-3xl">
                    {{ config('app.currency') }} {{ number_format((float) $stats['used_amount'], 2) }}
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-[1.5rem] bg-white p-4 shadow-xl ring-1 ring-black/5 sm:rounded-[2rem] sm:p-6">
            <div class="flex flex-col gap-4">
                <div>
                    <h2 class="text-xl font-black uppercase text-[#003b7a] sm:text-2xl">
                        {{ __('bonus.my_bonus_codes') }}
                    </h2>

                    <p class="mt-1 text-xs font-bold text-[#003b7a]/60 sm:text-sm">
                        {{ __('bonus.my_bonus_codes_desc') }}
                    </p>
                </div>

                <form method="GET" action="{{ route('bonus-codes.index') }}" class="grid w-full grid-cols-1 gap-2 sm:grid-cols-[1fr_1fr_auto]">
                    <select
                        name="status"
                        class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm"
                    >
                        <option value="all" @selected($status === 'all')>{{ __('bonus.all_status') }}</option>
                        <option value="unused" @selected($status === 'unused')>{{ __('bonus.status.unused') }}</option>
                        <option value="used" @selected($status === 'used')>{{ __('bonus.status.used') }}</option>
                        <option value="expired" @selected($status === 'expired')>{{ __('bonus.status.expired') }}</option>
                        <option value="cancelled" @selected($status === 'cancelled')>{{ __('bonus.status.cancelled') }}</option>
                    </select>

                    <select
                        name="type"
                        class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-3 py-2 text-xs font-black text-[#003b7a] outline-none sm:text-sm"
                    >
                        <option value="all" @selected($type === 'all')>{{ __('bonus.all_types') }}</option>
                        <option value="admin" @selected($type === 'admin')>{{ __('bonus.types.admin') }}</option>
                        <option value="invite" @selected($type === 'invite')>{{ __('bonus.types.invite') }}</option>
                        <option value="upgrade" @selected($type === 'upgrade')>{{ __('bonus.types.upgrade') }}</option>
                        <option value="compensation" @selected($type === 'compensation')>{{ __('bonus.types.compensation') }}</option>
                    </select>

                    <button
                        type="submit"
                        class="rounded-2xl bg-[#003b7a] px-5 py-2 text-xs font-black uppercase text-white sm:text-sm"
                    >
                        {{ __('bonus.filter') }}
                    </button>
                </form>
            </div>

            @if ($bonusCodes->isNotEmpty())
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($bonusCodes as $bonusCode)
                        @php
                            $isExpiredByTime = $bonusCode->expires_at && $bonusCode->expires_at->lessThan(now());

                            $isClaimable = $bonusCode->status === 'unused' && ! $isExpiredByTime;
                        @endphp

                        <article class="rounded-[1.25rem] bg-[#fff8c7] p-4 shadow-sm ring-1 ring-[#003b7a]/10">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="break-all text-lg font-black text-[#003b7a]">
                                        {{ $bonusCode->code }}
                                    </div>

                                    <div class="mt-1 text-xs font-bold text-[#003b7a]/60">
                                        {{ $bonusCode->created_at->format('d M Y, h:i A') }}
                                    </div>
                                </div>

                                <span class="shrink-0 rounded-full px-3 py-1 text-[10px] font-black uppercase
                                    @class([
                                        'bg-yellow-100 text-yellow-700' => $bonusCode->status === 'unused' && ! $isExpiredByTime,
                                        'bg-green-100 text-green-700' => $bonusCode->status === 'used',
                                        'bg-red-100 text-red-700' => in_array($bonusCode->status, ['expired', 'cancelled'], true) || $isExpiredByTime,
                                    ])">
                                    {{ $isExpiredByTime && $bonusCode->status === 'unused'
                                        ? __('bonus.status.expired')
                                        : __('bonus.status.' . $bonusCode->status) }}
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('bonus.amount') }}
                                    </div>

                                    <div class="mt-1 text-base font-black text-[#e4003a]">
                                        {{ config('app.currency') }} {{ number_format((float) $bonusCode->amount, 2) }}
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('bonus.type') }}
                                    </div>

                                    <div class="mt-1 text-base font-black text-[#003b7a]">
                                        {{ __('bonus.types.' . $bonusCode->type) }}
                                    </div>
                                </div>
                            </div>

                            @if ($bonusCode->remark)
                                <div class="mt-3 rounded-2xl bg-white p-3">
                                    <div class="text-[10px] font-black uppercase text-[#003b7a]/60">
                                        {{ __('bonus.remark') }}
                                    </div>

                                    <div class="mt-1 text-sm font-bold text-[#003b7a]/80">
                                        {{ $bonusCode->remark }}
                                    </div>
                                </div>
                            @endif

                            @if ($bonusCode->expires_at)
                                <div class="mt-3 text-xs font-bold text-[#003b7a]/60">
                                    {{ __('bonus.expires_at') }}:
                                    {{ $bonusCode->expires_at->format('d M Y, h:i A') }}
                                </div>
                            @endif

                            @if ($isClaimable)
                                <form method="POST" action="{{ route('bonus-codes.claim', $bonusCode) }}" class="mt-3">
                                    @csrf

                                    <button
                                        type="submit"
                                        class="w-full rounded-2xl bg-[#e4003a] px-4 py-3 text-xs font-black uppercase text-white shadow-lg"
                                    >
                                        {{ __('bonus.claim_now') }}
                                    </button>
                                </form>
                            @elseif ($bonusCode->used_at)
                                <div class="mt-3 rounded-2xl bg-green-100 p-3 text-xs font-black text-green-700">
                                    {{ __('bonus.used_at') }}:
                                    {{ $bonusCode->used_at->format('d M Y, h:i A') }}
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="mt-5">
                    {{ $bonusCodes->links() }}
                </div>
            @else
                <div class="mt-5 rounded-[1.25rem] bg-[#fff8c7] p-8 text-center">
                    <div class="text-2xl font-black text-[#003b7a]">
                        {{ __('bonus.no_bonus_codes') }}
                    </div>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('bonus.no_bonus_codes_desc') }}
                    </p>
                </div>
            @endif
        </div>
    </section>
@endsection