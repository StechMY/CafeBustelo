@php
    $items = [
        ['label' => __('navigation.dashboard'), 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => __('navigation.products'), 'route' => 'products.index', 'icon' => 'products'],
        ['label' => __('navigation.wallet'), 'route' => 'wallet.index', 'icon' => 'wallet'],
        // ['label' => __('navigation.check_in'), 'route' => 'check-in.index', 'icon' => 'check-in'],
        // ['label' => __('navigation.contact'), 'route' => 'contact.index', 'icon' => 'contact'],
        ['label' => __('navigation.team'), 'route' => 'my-team.index', 'icon' => 'team'],
        ['label' => __('golden_egg.title'), 'route' => 'golden-egg.index', 'icon' => 'golden-egg'],
        ['label' => __('navigation.profile'), 'route' => 'profile.index', 'icon' => 'profile'],
    ];

    $user = auth()->user();
@endphp

<header class="sticky top-0 z-40 border-b border-[#003b7a]/10 bg-[#003b7a] text-white shadow-sm">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-3 sm:px-6 lg:h-20 lg:px-8">
        <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-3">
            {{-- <img
                 src="{{ asset('logo.jpg') }}"
                 alt="{{ __('app.brand_name') }}"
                 class="h-11 w-11 rounded-full object-cover shadow-sm ring-2 ring-[#ffd900] lg:h-12 lg:w-12"> --}}

             <div
                 class="flex h-11 w-[120px] items-center justify-center rounded-2xl bg-[#ffd900] px-3 py-2 shadow-sm ring-1 ring-white/20 lg:h-12 lg:w-[170px]">
                <img
                     src="{{ asset('cafe-bustelo-logo.svg') }}"
                     alt="{{ __('app.brand_name') }}"
                     class="max-h-full max-w-full object-contain">
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
                        'class' => $item['icon'] === 'golden-egg' ? 'h-5 w-5' : 'h-4 w-4',
                    ])
                    <span class="whitespace-nowrap">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="hidden shrink-0 items-center gap-3 lg:flex">

            @include('partials.language-switcher')
            <div class="flex items-center gap-2 rounded-full bg-white/10 px-4 py-2">
                @php
                    $isActive = $user->status === 'active';
                @endphp

                <span
                      class="h-2.5 w-2.5 rounded-full {{ $isActive ? 'bg-[#00c853]' : 'bg-[#e53935]' }}"
                      aria-hidden="true"></span>

                <span class="text-xs font-black uppercase text-white">
                    {{ $isActive ? __('app.active_member_short') : __('app.inactive_account_short') }}
                </span>
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
