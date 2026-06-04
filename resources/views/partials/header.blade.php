@php
    $items = [
        ['label' => __('navigation.dashboard'), 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => __('navigation.products'), 'route' => 'products.index', 'icon' => 'products'],
        ['label' => __('navigation.wallet'), 'route' => 'wallet.index', 'icon' => 'wallet'],
        ['label' => __('navigation.check_in'), 'route' => 'check-in.index', 'icon' => 'check-in'],
        ['label' => __('navigation.contact'), 'route' => 'contact.index', 'icon' => 'contact'],
        ['label' => __('navigation.profile'), 'route' => 'profile.index', 'icon' => 'profile'],
    ];

    $user = auth()->user();
@endphp

<header class="sticky top-0 z-40 border-b border-[#003b7a]/10 bg-[#003b7a] text-white shadow-sm">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-3 sm:px-6 lg:h-20 lg:px-8">
        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-3">
            <img
                 src="{{ asset('logo.jpg') }}"
                 alt="{{ __('app.brand_name') }}"
                 class="h-11 w-11 rounded-full object-cover shadow-sm ring-2 ring-[#ffd900] lg:h-12 lg:w-12">

            <div class="leading-none">
                <div class="text-base font-black lg:text-lg">
                    {{ __('app.brand_name') }}
                </div>

                <div class="mt-1 text-[10px] font-black uppercase tracking-[0.22em] text-[#ffd900]">
                    {{ __('app.vip_club') }}
                </div>
            </div>
        </a>

        <nav class="hidden min-w-0 flex-1 items-center justify-center gap-1 xl:flex">
            @foreach ($items as $item)
                @php
                    $active = request()->routeIs($item['route']);
                @endphp

                <a
                   href="{{ route($item['route']) }}"
                   class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs font-black transition
                        {{ $active ? 'bg-[#ffd900] text-[#003b7a]' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    @include('partials.icon', [
                        'name' => $item['icon'],
                        'class' => 'h-4 w-4',
                    ])
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="hidden shrink-0 items-center gap-3 lg:flex">

            @include('partials.language-switcher')
            <div class="rounded-full bg-white/10 px-4 py-2">
                <div class="text-[10px] font-black uppercase tracking-wide text-white/50">
                    {{ __('app.account_status') }}
                </div>
                @php
                    $statusColor = $user->status === 'active' ? 'text-[#00c853]' : 'text-[#e53935]';
                @endphp
                <div class="text-xs font-black uppercase {{ $statusColor }}">
                    {{ $user->status === 'active' ? __('app.active_member') : __('app.inactive_account') }}
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button
                        type="submit"
                        class="rounded-full bg-white/10 px-4 py-2 text-xs font-black uppercase text-white transition hover:bg-[#ffd900] hover:text-[#003b7a]">
                    {{ __('app.logout') }}
                </button>
            </form>
        </div>

        <button
                type="button"
                class="grid h-11 w-11 place-items-center rounded-full bg-white/10 text-xl xl:hidden"
                onclick="window.dispatchEvent(new CustomEvent('open-mobile-menu'))"
                aria-label="{{ __('navigation.menu') }}">
            ☰
        </button>
    </div>
</header>
<script>
    let serverTime = new Date("{{ now()->toIso8601String() }}").getTime();

    function updateClock() {
        const now = new Date(serverTime);

        const formatted = now.toLocaleString('en-MY', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: '{{ config('app.timezone') }}',
        });

        const timeEl = document.getElementById('time');

        if (timeEl) {
            timeEl.textContent = formatted;
        }

        serverTime += 1000;
    }

    updateClock();
    setInterval(updateClock, 1000);
</script>
