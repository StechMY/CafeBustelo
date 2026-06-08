@extends('layouts.app')

@section('title', __('withdraw.submit_withdraw'))

@section('content')
    @php
        $currency = config('app.currency', 'RM');
        $feePercent = (float) ($withdrawFeePercent ?? 0);
    @endphp

    <section class="mx-auto max-w-3xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div class="rounded-[1.35rem] border-4 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.75rem] sm:p-7 sm:shadow-[8px_8px_0_#003b7a]">
            <div class="mb-4 sm:mb-6">
                <div class="mb-2 inline-flex rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase text-[#003b7a] sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                    {{ __('withdraw.withdraw_center') }}
                </div>

                <h1 class="text-2xl font-black uppercase leading-none text-[#003b7a] sm:text-4xl">
                    {{ __('withdraw.submit_withdraw') }}
                </h1>

                <p class="mt-2 line-clamp-2 text-xs font-bold text-[#003b7a]/70 sm:text-sm sm:line-clamp-none">
                    {{ __('withdraw.submit_desc') }}
                </p>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-2 sm:mb-5 sm:gap-3">
                <div class="rounded-xl bg-[#003b7a] p-3 text-white sm:rounded-2xl sm:p-4">
                    <div class="text-[9px] font-black uppercase text-white/60 sm:text-xs">
                        {{ __('dashboard.withdrawable_wallet') }}
                    </div>

                    <div class="mt-1 break-words text-xl font-black sm:text-2xl">
                        {{ $currency }} {{ number_format((float) $withdrawableBalance, 2) }}
                    </div>
                </div>

                <div class="rounded-xl bg-[#ffd900] p-3 text-[#003b7a] sm:rounded-2xl sm:p-4">
                    <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-xs">
                        {{ __('withdraw.locked_balance') }}
                    </div>

                    <div class="mt-1 break-words text-xl font-black sm:text-2xl">
                        {{ $currency }} {{ number_format((float) $lockedBalance, 2) }}
                    </div>
                </div>
            </div>

            <div class="mb-4 rounded-xl bg-[#fff8c7] p-3 ring-1 ring-[#003b7a]/10 sm:mb-5 sm:rounded-2xl sm:p-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-xs">
                            {{ __('withdraw.current_vip_level') }}
                        </div>

                        <div class="mt-1 text-sm font-black text-[#003b7a] sm:text-base">
                            LV{{ $vipLevel?->level ?? 1 }}
                        </div>
                    </div>

                    <div>
                        <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-xs">
                            {{ __('withdraw.min_withdraw_amount') }}
                        </div>

                        <div class="mt-1 text-sm font-black text-[#e4003a] sm:text-base">
                            {{ $currency }} {{ number_format((float) $minWithdrawAmount, 2) }}
                        </div>
                    </div>

                    <div>
                        <div class="text-[9px] font-black uppercase text-[#003b7a]/60 sm:text-xs">
                            {{ __('withdraw.withdraw_fee') }}
                        </div>

                        <div class="mt-1 text-sm font-black text-[#e4003a] sm:text-base">
                            {{ number_format($feePercent, 2) }}%
                        </div>
                    </div>
                </div>

                <div class="mt-3 rounded-xl bg-white p-3 text-xs font-bold leading-relaxed text-[#003b7a]/75">
                    {{ __('withdraw.security_fee_notice') }}
                </div>
            </div>

            <div class="mb-4 rounded-xl bg-[#003b7a] p-3 text-white sm:mb-5 sm:rounded-2xl sm:p-4">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[9px] font-black uppercase text-white/60 sm:text-xs">
                            {{ __('bank_account.bound_bank_account') }}
                        </div>

                        <div class="mt-1 truncate text-base font-black sm:text-lg">
                            {{ $bankAccount->bank_name }}
                        </div>

                        <div class="mt-1 text-xs font-bold text-white/70">
                            {{ $bankAccount->bank_account_name }} · {{ $bankAccount->maskedAccountNo() }}
                        </div>
                    </div>

                    <div class="shrink-0 rounded-full bg-[#ffd900] px-3 py-1 text-[10px] font-black uppercase text-[#003b7a]">
                        {{ __('bank_account.bound') }}
                    </div>
                </div>

                <div class="mt-3 rounded-xl bg-white/10 p-3 text-xs font-bold leading-relaxed text-white/75">
                    {{ __('bank_account.change_notice') }}
                </div>
            </div>

            <form method="POST" action="{{ route('withdraw.store') }}" class="space-y-3 sm:space-y-4">
                @csrf

                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                        {{ __('withdraw.amount') }}
                    </label>

                    <input
                        name="amount"
                        value="{{ old('amount') }}"
                        type="number"
                        step="0.01"
                        min="{{ max(1, (float) $minWithdrawAmount) }}"
                        max="{{ (float) $withdrawableBalance }}"
                        class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                        placeholder="{{ number_format(max(1, (float) $minWithdrawAmount), 2, '.', '') }}"
                        required
                    >

                    @error('amount')
                        <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                    @enderror

                    <div class="mt-2 text-[10px] font-bold text-[#003b7a]/60 sm:text-xs">
                        {{ __('withdraw.net_amount_hint') }}
                    </div>
                </div>

                <div class="rounded-xl bg-[#ffd900] p-3 text-xs font-bold leading-relaxed text-[#003b7a] sm:rounded-2xl sm:p-4 sm:text-sm">
                    {{ __('withdraw.warning_desc') }}
                </div>

                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <a
                        href="{{ route('withdraw.index') }}"
                        class="rounded-xl bg-gray-100 px-4 py-3 text-center text-xs font-black uppercase text-gray-700 sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm"
                    >
                        {{ __('withdraw.back') }}
                    </a>

                    <button
                        type="submit"
                        class="rounded-xl bg-[#e4003a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#9e0028] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm sm:shadow-[0_6px_0_#9e0028]"
                    >
                        {{ __('withdraw.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection