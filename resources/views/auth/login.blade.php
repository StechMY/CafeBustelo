@extends('layouts.guest')

@section('title', __('auth.login'))

@section('content')
    <section
             class="mx-auto grid min-h-[calc(100vh-80px)] max-w-6xl items-center gap-7 px-4 py-6 sm:px-6 sm:py-10 lg:min-h-[calc(100vh-96px)] lg:grid-cols-2 lg:gap-10">
        <div>
            <div
                 class="mb-4 inline-flex rounded-full bg-white/60 px-4 py-2 text-xs font-black uppercase tracking-wide shadow sm:mb-6 sm:text-sm">
                {{ __('auth.login_badge') }}
            </div>

            <h1 class="max-w-xl text-4xl font-black uppercase leading-[0.9] tracking-tight sm:text-6xl lg:text-8xl">
                {{ __('auth.welcome_back') }}
            </h1>

            <p class="mt-4 max-w-lg text-sm font-bold leading-relaxed text-[#003b7a]/80 sm:mt-6 sm:text-lg">
                {{ __('auth.login_description') }}
            </p>

            <div class="mt-6 grid max-w-lg grid-cols-1 gap-3 sm:mt-8 sm:grid-cols-3">
                <div class="rounded-2xl bg-white/70 p-4 shadow-xl sm:rounded-3xl">
                    <div class="text-2xl font-black">{{ __('dashboard.daily') }}</div>
                    <div class="text-xs font-bold uppercase text-[#e4003a]">{{ __('dashboard.income') }}</div>
                </div>

                <div class="rounded-2xl bg-[#2148ff] p-4 text-white shadow-xl sm:rounded-3xl">
                    <div class="text-2xl font-black">{{ __('dashboard.vip') }}</div>
                    <div class="text-xs font-bold uppercase text-white/80">{{ __('dashboard.level') }}</div>
                </div>

                <div class="rounded-2xl bg-[#e4003a] p-4 text-white shadow-xl sm:rounded-3xl">
                    <div class="text-2xl font-black">{{ __('dashboard.team') }}</div>
                    <div class="text-xs font-bold uppercase text-white/80">{{ __('dashboard.bonus') }}</div>
                </div>
            </div>
        </div>

        <div class="mx-auto w-full max-w-md">
            <div
                 class="rounded-[1.5rem] border-4 border-[#003b7a] bg-white p-5 shadow-[6px_6px_0_#003b7a] sm:rounded-[2rem] sm:p-7 sm:shadow-[12px_12px_0_#003b7a]">
                <div class="mb-8 text-center">
                    <img
                         src="{{ asset('logo.jpg') }}"
                         alt="{{ __('app.brand_name') }}"
                         class="h-11 w-11 rounded-full object-cover shadow-sm ring-2 ring-[#ffd900] lg:h-12 lg:w-12">

                    <h2 class="text-2xl font-black uppercase sm:text-3xl">
                        {{ __('auth.member_login') }}
                    </h2>

                    <p class="mt-2 text-sm font-bold text-[#003b7a]/70">
                        {{ __('auth.login_subtitle') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                    @csrf

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
                               placeholder="{{ __('auth.username_placeholder') }}"
                               class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 text-base font-bold outline-none transition focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                               required>

                        @error('username')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-black uppercase">
                            {{ __('auth.password') }}
                        </label>

                        <input
                               id="password"
                               name="password"
                               type="password"
                               autocomplete="current-password"
                               placeholder="{{ __('auth.password_placeholder') }}"
                               class="w-full rounded-2xl border-2 border-[#003b7a] bg-[#fff8c7] px-4 py-3.5 text-base font-bold outline-none transition focus:bg-white focus:ring-4 focus:ring-[#ffd900] sm:px-5 sm:py-4"
                               required>

                        @error('password')
                            <div class="mt-2 text-sm font-bold text-[#e4003a]">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <label class="flex items-center gap-3 text-sm font-bold">
                        <input
                               name="remember"
                               type="checkbox"
                               value="1"
                               class="h-5 w-5 rounded border-[#003b7a] text-[#e4003a] focus:ring-[#ffd900]">
                        {{ __('auth.remember_me') }}
                    </label>

                    <button
                            type="submit"
                            class="w-full rounded-2xl bg-[#e4003a] px-6 py-4 text-base font-black uppercase text-white shadow-[0_6px_0_#9e0028] transition hover:-translate-y-0.5 sm:text-lg sm:shadow-[0_8px_0_#9e0028]">
                        {{ __('auth.login_now') }}
                    </button>
                </form>

                <div class="mt-8 text-center text-sm font-bold flex justify-between">
                    <div class="">
                        {{ __('auth.no_account') }}
                        <a href="{{ route('register') }}" class="font-black text-[#e4003a] underline">
                            {{ __('auth.register_now') }}
                        </a>
                    </div>
                    <div class="">
                        <a href="{{ route('contact.index') }}" class="font-black text-[#e4003a] underline">
                            {{ __('contact.contact_us') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
