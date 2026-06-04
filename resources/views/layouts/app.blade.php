<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ sidebarOpen: false }">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/fav.png" type="image/png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('app.brand_name') }}">
    <meta property="og:title" content="Café Bustelo® Coffee: Experience Sabor Latino">
    <meta property="og:description" content="Our espresso style coffee invites you to enjoy the moment and celebrate Hispanic culture—with delicious experiences that are even better when shared.">
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
</body>

</html>
