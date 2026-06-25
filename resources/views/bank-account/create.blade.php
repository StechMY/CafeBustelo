@extends('layouts.app')

@section('title', __('bank_account.bind_bank_account'))

@section('content')
    <section class="mx-auto max-w-3xl px-3 py-4 sm:px-6 sm:py-8 lg:px-8">
        <div
             class="rounded-[1.35rem] border-4 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[1.75rem] sm:p-7 sm:shadow-[8px_8px_0_#003b7a]">
            <div class="mb-4 sm:mb-6">
                <div
                     class="mb-2 inline-flex rounded-full bg-[#ffd900] px-3 py-1.5 text-[10px] font-black uppercase text-[#003b7a] sm:mb-3 sm:px-4 sm:py-2 sm:text-xs">
                    {{ __('bank_account.security_binding') }}
                </div>

                <h1 class="text-2xl font-black uppercase leading-none text-[#003b7a] sm:text-4xl">
                    {{ __('bank_account.bind_bank_account') }}
                </h1>

                <p class="mt-2 text-xs font-bold leading-relaxed text-[#003b7a]/70 sm:text-sm">
                    {{ __('bank_account.bind_desc') }}
                </p>
            </div>

            <div
                 class="mb-4 rounded-xl bg-[#ffd900] p-3 text-xs font-black leading-relaxed text-[#003b7a] sm:mb-5 sm:rounded-2xl sm:p-4 sm:text-sm">
                ⚠️ {{ __('bank_account.security_warning') }}
            </div>

            <form method="POST" action="{{ route('bank-account.store') }}" class="space-y-3 sm:space-y-4">
                @csrf

                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                        {{ __('bank_account.real_name') }}
                    </label>

                    <input
                           name="bank_account_name"
                           value="{{ old('bank_account_name', $user->name) }}"
                           type="text"
                           class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                           placeholder="{{ __('bank_account.real_name_placeholder') }}"
                           required>

                    @error('bank_account_name')
                        <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                        {{ __('bank_account.bank_name') }}
                    </label>

                    <input
                           name="bank_name"
                           value="{{ old('bank_name') }}"
                           type="text"
                           class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                           placeholder="{{ __('bank_account.bank_name_placeholder') }}"
                           required>

                    @error('bank_name')
                        <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                        {{ __('bank_account.bank_account_no') }}
                    </label>

                    <input
                           name="bank_account_no"
                           value="{{ old('bank_account_no') }}"
                           type="text"
                           inputmode="numeric"
                           class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                           placeholder="{{ __('bank_account.bank_account_no_placeholder') }}"
                           required>

                    @error('bank_account_no')
                        <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                        {{ __('bank_account.phone') }}
                    </label>

                    <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                        <input
                               id="bank_phone"
                               name="phone"
                               value="{{ old('phone', $user->phone) }}"
                               type="text"
                               class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                               placeholder="{{ __('bank_account.phone_placeholder') }}"
                               required>
                        <div data-otp-message class="mt-1.5 hidden text-xs font-bold sm:text-sm"></div>
                        <button
                                type="button"
                                data-otp-send
                                data-otp-url="{{ route('bank-account.otp.send') }}"
                                data-phone-input="bank_phone"
                                class="rounded-xl bg-[#003b7a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#001f40] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm">
                            {{ __('otp.send_code') }}
                        </button>
                    </div>

                    @error('phone')
                        <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-black uppercase text-[#003b7a] sm:mb-2 sm:text-sm">
                        {{ __('otp.verification_code') }}
                    </label>

                    <input
                           name="otp_code"
                           value="{{ old('otp_code') }}"
                           type="text"
                           inputmode="numeric"
                           maxlength="6"
                           class="w-full rounded-xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3 text-sm font-bold text-[#003b7a] outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-base"
                           placeholder="{{ __('otp.code_placeholder') }}"
                           required>

                    @error('otp_code')
                        <div class="mt-1.5 text-xs font-bold text-[#e4003a] sm:text-sm">{{ $message }}</div>
                    @enderror
                </div>
                <div
                     class="rounded-xl bg-[#fff8c7] p-3 text-xs font-bold leading-relaxed text-[#003b7a]/75 ring-1 ring-[#003b7a]/10 sm:rounded-2xl sm:p-4 sm:text-sm">
                    {{ __('bank_account.change_notice') }}
                </div>

                <div class="grid grid-cols-2 gap-2 sm:gap-3">
                    <a
                       href="{{ route('withdraw.index') }}"
                       class="rounded-xl bg-gray-100 px-4 py-3 text-center text-xs font-black uppercase text-gray-700 sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm">
                        {{ __('app.back') }}
                    </a>

                    <button
                            type="submit"
                            class="rounded-xl bg-[#e4003a] px-4 py-3 text-xs font-black uppercase text-white shadow-[0_4px_0_#9e0028] sm:rounded-2xl sm:px-5 sm:py-4 sm:text-sm sm:shadow-[0_6px_0_#9e0028]">
                        {{ __('bank_account.bind_now') }}
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
