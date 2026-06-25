<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ sidebarOpen: false }">

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

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @stack('styles')
</head>

<body class="min-h-screen overflow-x-hidden bg-[#f7f7f7] text-[#003b7a] antialiased">
    @auth
        @include('partials.mobile-menu')
    @endauth

    <div class="min-h-screen lg:flex">

        <div class="min-w-0 w-full flex-1 overflow-x-hidden">
            @auth
                @include('partials.header')
            @endauth

            <main class="w-full overflow-x-hidden pb-24 lg:pb-10">
                @include('partials.toast')

                @yield('content')
            </main>
        </div>
    </div>
    @auth
        @include('partials.bottom-nav')
    @endauth
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if (session('success'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: @json(session('success')),
                    showConfirmButton: false,
                    timer: 2600,
                    timerProgressBar: true,
                });
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: @json(session('error')),
                    showConfirmButton: false,
                    timer: 3200,
                    timerProgressBar: true,
                });
            });
        </script>
    @endif

    @stack('scripts')

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
