@extends('layouts.guest')

@section('title', __('auth.register'))

@section('content')
    <section
             class="mx-auto grid min-h-[calc(100vh-80px)] max-w-6xl items-center gap-7 px-4 py-6 sm:px-6 sm:py-10 lg:min-h-[calc(100vh-96px)] lg:grid-cols-2 lg:gap-10">
        <div class="order-2 lg:order-1">
            <div
                 class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-5 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:p-7 sm:shadow-[12px_12px_0_#003b7a]">
                <div class="mb-6 sm:mb-8">
                    <div
                         class="mb-4 inline-flex rounded-full bg-[#ffd900] px-4 py-2 text-xs font-black uppercase tracking-wide">
                        {{ __('auth.register_badge') }}
                    </div>

                    <h1 class="text-3xl font-black uppercase leading-none sm:text-4xl">
                        {{ __('auth.create_account') }}
                    </h1>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('auth.register_description') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('register.store') }}" class="grid gap-5">
                    @csrf

                    <div>
                        <label for="name" class="mb-2 block text-sm font-black uppercase">
                            {{ __('auth.full_name') }}
                        </label>

                        <input
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               type="text"
                               placeholder="{{ __('auth.full_name_placeholder') }}"
                               class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 font-bold outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                               required>

                        @error('name')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="username" class="mb-2 block text-sm font-black uppercase">
                            {{ __('auth.username') }}
                        </label>

                        <input
                               id="username"
                               name="username"
                               value="{{ old('username') }}"
                               type="text"
                               autocomplete="username"
                               placeholder="{{ __('auth.create_username_placeholder') }}"
                               class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 font-bold outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                               required>

                        @error('username')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="mb-2 block text-sm font-black uppercase">
                            {{ __('auth.phone') }}
                        </label>

                        <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <div
                                 class="flex overflow-hidden rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#ffd900]">
                                <div
                                     class="flex items-center border-r-2 border-[#003b7a] bg-[#003b7a] px-4 text-sm font-black text-white sm:px-5">
                                    +84
                                </div>

                                <input
                                       id="phone"
                                       name="phone"
                                       value="{{ old('phone') }}"
                                       type="text"
                                       required
                                       placeholder="{{ __('auth.phone_placeholder') }}"
                                       class="min-w-0 flex-1 bg-transparent px-4 py-3.5 font-bold text-[#003b7a] outline-none sm:px-5 sm:py-4">
                            </div>

                            <button
                                    type="button"
                                    data-otp-send
                                    data-otp-url="{{ route('register.otp.send') }}"
                                    data-phone-input="phone"
                                    class="rounded-2xl bg-[#003b7a] px-4 py-3.5 text-sm font-black uppercase text-white shadow-[0_4px_0_#001f40] sm:px-5 sm:py-4">
                                {{ __('otp.send_code') }}
                            </button>
                        </div>

                        <div data-otp-message class="mt-2 hidden text-sm font-bold"></div>
                        <x-otp-challenge purpose="register" />

                        {{-- <p class="mt-2 text-xs font-bold text-[#003b7a]/60">
                            {{ __('otp.phone_prefix_hint') }}
                        </p> --}}

                        @error('phone')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="otp_code" class="mb-2 block text-sm font-black uppercase">
                            {{ __('otp.verification_code') }}
                        </label>

                        <input
                               id="otp_code"
                               name="otp_code"
                               value="{{ old('otp_code') }}"
                               type="text"
                               inputmode="numeric"
                               maxlength="6"
                               placeholder="{{ __('otp.code_placeholder') }}"
                               class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 font-bold outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                               required>

                        @error('otp_code')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="referral_code" class="mb-2 block text-sm font-black uppercase">
                            {{ __('auth.referral_code') }}
                        </label>

                        <input
                               id="referral_code"
                               name="referral_code"
                               value="{{ old('referral_code', $referralCode ?? '') }}"
                               type="text"
                               placeholder="{{ __('auth.referral_code_placeholder') }}"
                               required
                               class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 font-bold uppercase outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4">

                        @error('referral_code')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div>
                            <label for="password" class="mb-2 block text-sm font-black uppercase">
                                {{ __('auth.password') }}
                            </label>

                            <input
                                   id="password"
                                   name="password"
                                   type="password"
                                   autocomplete="new-password"
                                   placeholder="{{ __('auth.password_placeholder') }}"
                                   class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 font-bold outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                                   required>

                            @error('password')
                                <div class="mt-2 text-sm font-bold text-[#e4003a]">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-black uppercase">
                                {{ __('auth.confirm_password') }}
                            </label>

                            <input
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   type="password"
                                   autocomplete="new-password"
                                   placeholder="{{ __('auth.confirm_password_placeholder') }}"
                                   class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 font-bold outline-none focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                                   required>
                        </div>
                    </div>

                    <button
                            type="submit"
                            class="rounded-2xl bg-[#e4003a] px-6 py-4 text-base font-black uppercase text-white shadow-[0_6px_0_#9e0028] transition hover:-translate-y-0.5 sm:text-lg sm:shadow-[0_8px_0_#9e0028]">
                        {{ __('auth.register_now') }}
                    </button>
                </form>

                <div class="mt-8 text-center text-sm font-bold">
                    {{ __('auth.already_have_account') }}
                    <a href="{{ route('login') }}" class="font-black text-[#e4003a] underline">
                        {{ __('auth.login') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="order-1 lg:order-2">
            <div
                 class="relative overflow-hidden rounded-[1.75rem] bg-[#2148ff] p-5 text-white shadow-2xl sm:rounded-[2.5rem] sm:p-8">
                <div class="absolute -right-16 -top-16 h-52 w-52 rounded-full bg-[#ffd900] opacity-90"></div>
                <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-[#e4003a] opacity-80"></div>

                <div class="relative z-10">
                    <div
                         class="mb-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-black uppercase text-[#003b7a] sm:mb-6 sm:text-sm">
                        {{ __('dashboard.vip_member_platform') }}
                    </div>

                    <h2 class="max-w-md text-4xl font-black uppercase leading-[0.9] tracking-tight sm:text-6xl">
                        {{ __('auth.register_hero_title') }}
                    </h2>

                    <p class="mt-4 max-w-md text-sm font-bold leading-relaxed text-white/85 sm:mt-6 sm:text-base">
                        {{ __('auth.register_hero_description') }}
                    </p>

                    <div class="mt-6 grid gap-3 sm:mt-8 sm:grid-cols-2 sm:gap-4">
                        <div class="rounded-2xl bg-white/15 p-4 backdrop-blur sm:rounded-3xl sm:p-5">
                            <div class="text-2xl font-black sm:text-3xl">B/C/D</div>
                            <div class="text-sm font-bold uppercase text-white/75">
                                {{ __('dashboard.team_commission') }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/15 p-4 backdrop-blur sm:rounded-3xl sm:p-5">
                            <div class="text-2xl font-black sm:text-3xl">LV1-LV8</div>
                            <div class="text-sm font-bold uppercase text-white/75">
                                {{ __('dashboard.vip_levels') }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/15 p-4 backdrop-blur sm:rounded-3xl sm:p-5">
                            <div class="text-2xl font-black sm:text-3xl">{{ __('dashboard.daily') }}</div>
                            <div class="text-sm font-bold uppercase text-white/75">
                                {{ __('dashboard.income_claims') }}
                            </div>
                        </div>

                        <div class="rounded-2xl bg-white/15 p-4 backdrop-blur sm:rounded-3xl sm:p-5">
                            <div class="text-2xl font-black sm:text-3xl">{{ __('dashboard.bonus') }}</div>
                            <div class="text-sm font-bold uppercase text-white/75">
                                {{ __('dashboard.invite_rewards') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
