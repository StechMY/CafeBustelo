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

    @include('partials.otp-scripts')
</body>

</html>
