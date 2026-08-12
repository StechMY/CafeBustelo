@extends('layouts.app')

@section('title', __('password.change_password'))

@section('content')
    <section class="bg-[#ffd900]">
        <div class="mx-auto max-w-5xl px-3 py-5 sm:px-6 sm:py-10 lg:px-8">
            <div class="grid gap-4 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div>
                    <div
                         class="inline-flex rounded-full bg-white/75 px-3 py-1.5 text-[10px] font-black uppercase tracking-wide text-[#003b7a] shadow sm:px-5 sm:py-2 sm:text-xs sm:tracking-[0.22em]">
                        {{ __('password.security_verification') }}
                    </div>

                    <h1
                        class="mt-3 text-3xl font-black uppercase leading-[0.9] tracking-tight text-[#003b7a] sm:mt-4 sm:text-6xl">
                        {{ __('password.change_password') }}
                    </h1>

                    <p
                       class="mt-2 max-w-2xl text-xs font-bold leading-relaxed text-[#003b7a]/75 sm:mt-5 sm:text-base">
                        {{ __('password.change_password_desc') }}
                    </p>
                </div>

                <div
                     class="rounded-[1.35rem] border-3 border-[#003b7a] bg-white p-4 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:border-4 sm:p-6 sm:shadow-[10px_10px_0_#003b7a]">
                    <div class="text-[10px] font-black uppercase tracking-[0.16em] text-[#e4003a]">
                        {{ __('password.otp_will_send_to') }}
                    </div>

                    <div class="mt-2 break-words text-2xl font-black text-[#003b7a] sm:text-3xl">
                        {{ $user->phone ?: '-' }}
                    </div>

                    @error('phone')
                        <div class="mt-3 rounded-2xl bg-red-100 p-3 text-sm font-black text-red-700">
                            {{ $message }}
                        </div>
                    @enderror

                    <form method="POST" action="{{ route('settings.password.otp.send') }}" class="mt-4">
                        @csrf

                        <button
                                type="submit"
                                @disabled(blank($user->phone))
                                class="w-full rounded-2xl px-5 py-3 text-sm font-black uppercase shadow-lg transition
                                {{ filled($user->phone)
                                    ? 'bg-[#003b7a] text-white hover:-translate-y-0.5 hover:bg-[#002b59]'
                                    : 'cursor-not-allowed bg-gray-200 text-gray-500' }}">
                            {{ __('otp.send_code') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-3 py-6 sm:px-6 sm:py-8 lg:px-8">
        <div class="rounded-[2rem] bg-white p-5 shadow-xl ring-1 ring-black/5 sm:p-6">
            <form method="POST" action="{{ route('settings.password.update') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="otp_code" class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                        {{ __('otp.verification_code') }}
                    </label>

                    <input
                           id="otp_code"
                           type="text"
                           name="otp_code"
                           value="{{ old('otp_code') }}"
                           inputmode="numeric"
                           maxlength="6"
                           autocomplete="one-time-code"
                           class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-4 py-3 text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]"
                           placeholder="{{ __('otp.code_placeholder') }}"
                           required>

                    @error('otp_code')
                        <p class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                        {{ __('password.new_password') }}
                    </label>

                    <input
                           id="password"
                           type="password"
                           name="password"
                           autocomplete="new-password"
                           class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-4 py-3 text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]"
                           placeholder="{{ __('password.new_password_placeholder') }}"
                           required>

                    @error('password')
                        <p class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-2 block text-xs font-black uppercase text-[#003b7a]/65">
                        {{ __('password.confirm_new_password') }}
                    </label>

                    <input
                           id="password_confirmation"
                           type="password"
                           name="password_confirmation"
                           autocomplete="new-password"
                           class="w-full rounded-2xl border-2 border-[#003b7a]/15 bg-[#fff8c7] px-4 py-3 text-lg font-black text-[#003b7a] outline-none focus:border-[#003b7a]"
                           placeholder="{{ __('password.confirm_new_password_placeholder') }}"
                           required>

                    @error('password_confirmation')
                        <p class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</p>
                    @enderror
                </div>

                <button
                        type="submit"
                        @disabled(blank($user->phone))
                        class="w-full rounded-2xl px-5 py-3 text-sm font-black uppercase shadow-lg transition
                        {{ filled($user->phone)
                            ? 'bg-[#e4003a] text-white hover:-translate-y-0.5 hover:bg-[#c90032]'
                            : 'cursor-not-allowed bg-gray-200 text-gray-500' }}">
                    {{ __('password.update_password') }}
                </button>
            </form>
        </div>
    </section>
@endsection
