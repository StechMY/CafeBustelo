@php
    $items = [
        ['label' => __('navigation.home'), 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => __('navigation.contact'), 'route' => 'contact.index', 'icon' => 'contact'],
        ['label' => __('navigation.products'), 'route' => 'products.index', 'icon' => 'products'],
        ['label' => __('navigation.check_in'), 'route' => 'check-in.index', 'icon' => 'check-in'],
        ['label' => __('navigation.profile'), 'route' => 'profile.index', 'icon' => 'profile'],
    ];
@endphp

<nav
     class="fixed bottom-0 left-0 right-0 z-40 border-t border-[#003b7a]/10 bg-white shadow-[0_-10px_30px_rgba(0,0,0,0.08)] lg:hidden">
    <div class="grid grid-cols-5">
        @foreach ($items as $item)
            @php
                $active = request()->routeIs($item['route']);
            @endphp

            <a
               href="{{ route($item['route']) }}"
               class="flex flex-col items-center gap-1 px-1 py-2.5 text-[10px] font-black transition
                    {{ $active ? 'text-[#e4003a]' : 'text-[#003b7a]/55' }}">
                <span
                      class="grid h-8 w-8 place-items-center rounded-2xl transition
                    {{ $active ? 'bg-[#ffd900] text-[#003b7a]' : 'bg-transparent' }}">
                    @include('partials.icon', [
                        'name' => $item['icon'],
                        'class' => 'h-5 w-5',
                    ])
                </span>

                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
