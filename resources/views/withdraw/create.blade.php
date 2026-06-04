@extends('layouts.app')

@section('title', __('withdraw.submit_withdraw'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-6 sm:px-6 lg:px-8">
        <div class="rounded-[1.75rem] border-4 border-[#003b7a] bg-white p-5 shadow-[8px_8px_0_#003b7a] sm:p-7">
            <div class="mb-6">
                <div
                     class="mb-3 inline-flex rounded-full bg-[#ffd900] px-4 py-2 text-xs font-black uppercase text-[#003b7a]">
                    {{ __('withdraw.withdraw_center') }}
                </div>

                <h1 class="text-3xl font-black uppercase text-[#003b7a] sm:text-4xl">
                    {{ __('withdraw.submit_withdraw') }}
                </h1>

                <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                    {{ __('withdraw.submit_desc') }}
                </p>
            </div>
            <div class="mb-5 rounded-2xl bg-white p-4 ring-1 ring-black/5">
                <div class="text-xs font-black uppercase text-[#003b7a]/60">
                    {{ __('withdraw.current_vip_level') }}
                </div>

                <div class="mt-1 text-xl font-black text-[#003b7a]">
                    LV{{ $vipLevel?->level ?? 1 }}
                </div>

                <div class="mt-2 text-sm font-bold text-[#003b7a]/70">
                    {{ __('withdraw.min_withdraw_amount') }}:
                    {{ config('app.currency') }} {{ number_format((float) $minWithdrawAmount, 2) }}
                </div>
            </div>

            <div class="mb-5 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-[#003b7a] p-4 text-white">
                    <div class="text-xs font-black uppercase text-white/60">
                        {{ __('dashboard.withdrawable_wallet') }}
                    </div>
                    <div class="mt-1 text-2xl font-black">
                        {{ config('app.currency') }} {{ number_format((float) $withdrawableBalance, 2) }}
                    </div>
                </div>

                <div class="rounded-2xl bg-[#ffd900] p-4 text-[#003b7a]">
                    <div class="text-xs font-black uppercase text-[#003b7a]/60">
                        {{ __('withdraw.locked_balance') }}
                    </div>
                    <div class="mt-1 text-2xl font-black">
                        {{ config('app.currency') }} {{ number_format((float) $lockedBalance, 2) }}
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('withdraw.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('withdraw.amount') }}
                    </label>

                    <input
                           name="amount"
                           value="{{ old('amount') }}"
                           type="number"
                           step="0.01"
                           min="{{ max(1, (float) $minWithdrawAmount) }}"
                           max="{{ (float) $withdrawableBalance }}"
                           class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                           placeholder="{{ number_format(max(1, (float) $minWithdrawAmount), 2, '.', '') }}"
                           required>

                    @error('amount')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('withdraw.bank_name') }}
                    </label>

                    <input
                           name="bank_name"
                           value="{{ old('bank_name') }}"
                           type="text"
                           class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                           placeholder="{{ __('withdraw.bank_name_placeholder') }}"
                           required>

                    @error('bank_name')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('withdraw.bank_account_name') }}
                    </label>

                    <input
                           name="bank_account_name"
                           value="{{ old('bank_account_name', $user->name) }}"
                           type="text"
                           class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                           placeholder="{{ __('withdraw.bank_account_name_placeholder') }}"
                           required>

                    @error('bank_account_name')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-black uppercase text-[#003b7a]">
                        {{ __('withdraw.bank_account_no') }}
                    </label>

                    <input
                           name="bank_account_no"
                           value="{{ old('bank_account_no') }}"
                           type="text"
                           class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-5 py-4 font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900]"
                           placeholder="{{ __('withdraw.bank_account_no_placeholder') }}"
                           required>

                    @error('bank_account_no')
                        <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                    @enderror
                </div>

                <div class="rounded-2xl bg-[#ffd900] p-4 text-sm font-bold text-[#003b7a]">
                    {{ __('withdraw.warning_desc') }}
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <a href="{{ route('withdraw.index') }}"
                       class="rounded-2xl bg-gray-100 px-5 py-4 text-center text-sm font-black uppercase text-gray-700">
                        {{ __('withdraw.back') }}
                    </a>

                    <button
                            type="submit"
                            class="rounded-2xl bg-[#e4003a] px-5 py-4 text-sm font-black uppercase text-white shadow-[0_6px_0_#9e0028]">
                        {{ __('withdraw.submit') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
