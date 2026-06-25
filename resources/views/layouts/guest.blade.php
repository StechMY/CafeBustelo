<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/fav.png" type="image/png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Caf𝑒Bustelᴏ">
    <meta property="og:title" content="Caf𝑒 Bustelᴏ® Coffee: Taste the Latin vibe.">
    <meta property="og:description"
          content="Sip, savor, and celebrate Hispanic culture together. Our espresso-style coffee is crafted to bring people together, creating delicious moments that are even better when shared.">
    <meta property="og:image" content="{{ url('/logo.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <title>@yield('title', config('app.name'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#ffd900] text-[#003b7a] antialiased">
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/30 blur-3xl"></div>
        <div class="absolute right-0 top-20 h-96 w-96 rounded-full bg-[#2148ff]/20 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-96 w-96 rounded-full bg-[#e4003a]/20 blur-3xl"></div>
    </div>

    <header class="sticky top-0 z-40 border-b border-[#003b7a]/10 bg-[#003b7a] text-white shadow-sm">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-3 sm:px-6 lg:h-20 lg:px-8">
            <a href="{{ route('login') }}" class="flex shrink-0 items-center gap-3">
                <div class="grid h-11 w-11 place-items-center rounded-full bg-[#ffd900] p-1 shadow-sm lg:h-12 lg:w-12">
                    <img
                         src="{{ asset('logo.jpg') }}"
                         alt="{{ __('app.brand_name') }}"
                         class="h-full w-full rounded-full object-contain">
                </div>

                <div class="leading-none">
                    <div class="text-base font-black lg:text-lg">
                        {{ __('app.brand_name') }}
                    </div>

                    <div class="mt-1 text-[10px] font-black uppercase tracking-[0.22em] text-[#ffd900]">
                        {{ __('app.vip_club') }}
                    </div>
                </div>
            </a>

            <div class="flex items-center gap-2 text-xs font-black sm:gap-3 sm:text-sm">
                @include('partials.language-switcher')

                <a
                   href="{{ route('contact.index') }}"
                   class="hidden rounded-full px-4 py-2 text-white/85 transition hover:bg-white/10 hover:text-white md:inline-flex">
                    {{ __('navigation.contact') }}
                </a>

                <a
                   href="{{ route('login') }}"
                   class="hidden rounded-full px-4 py-2 text-white/85 transition hover:bg-white/10 hover:text-white sm:inline-flex sm:px-5">
                    {{ __('common.login') }}
                </a>

                <a
                   href="{{ route('register') }}"
                   class="rounded-full bg-[#e4003a] px-4 py-2 text-white shadow-lg transition hover:bg-[#c90032] sm:px-5">
                    {{ __('common.register') }}
                </a>
            </div>
        </div>
    </header>

    <main class="relative z-10">
        @include('partials.toast')

        @yield('content')
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            document.querySelectorAll('[data-otp-send]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const url = button.dataset.otpUrl;
                    const phoneInputId = button.dataset.phoneInput;
                    const phoneInput = document.getElementById(phoneInputId);
                    const wrapper = button.closest('div');
                    const messageBox = wrapper?.parentElement?.querySelector(
                            '[data-otp-message]') ||
                        document.querySelector('[data-otp-message]');

                    const phone = phoneInput?.value?.trim();

                    if (!phone) {
                        showOtpMessage(messageBox, '{{ __('otp.phone_required') }}', false);
                        return;
                    }

                    button.disabled = true;
                    const originalText = button.textContent;
                    button.textContent = '{{ __('otp.sending') }}';

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                phone
                            }),
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            const errorMessage = data.message ||
                                Object.values(data.errors || {})?.[0]?.[0] ||
                                '{{ __('otp.sms_send_failed') }}';

                            showOtpMessage(messageBox, errorMessage, false);
                            button.disabled = false;
                            button.textContent = originalText;
                            return;
                        }

                        showOtpMessage(messageBox, data.message || '{{ __('otp.sent') }}',
                        true);

                        startOtpCountdown(button, originalText, Number(data.resend_seconds ||
                            60));
                    } catch (error) {
                        showOtpMessage(messageBox, '{{ __('otp.sms_send_failed') }}', false);
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                });
            });

            function showOtpMessage(element, message, success) {
                if (!element) return;

                element.classList.remove('hidden');
                element.textContent = message;
                element.classList.toggle('text-[#003b7a]', success);
                element.classList.toggle('text-[#e4003a]', !success);
            }

            function startOtpCountdown(button, originalText, seconds) {
                let remaining = seconds;

                button.textContent = `${remaining}s`;

                const timer = setInterval(() => {
                    remaining -= 1;

                    if (remaining <= 0) {
                        clearInterval(timer);
                        button.disabled = false;
                        button.textContent = originalText;
                        return;
                    }

                    button.textContent = `${remaining}s`;
                }, 1000);
            }
        });
    </script>
</body>

</html>
