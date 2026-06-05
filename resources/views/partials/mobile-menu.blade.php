@php
    $groups = [
        [
            'label' => __('navigation.main'),
            'items' => [
                ['label' => __('navigation.dashboard'), 'route' => 'dashboard', 'icon' => 'home'],
                ['label' => __('navigation.products'), 'route' => 'products.index', 'icon' => 'products'],
                ['label' => __('navigation.check_in'), 'route' => 'check-in.index', 'icon' => 'check-in'],
            ],
        ],
        [
            'label' => __('navigation.finance'),
            'items' => [
                ['label' => __('navigation.wallet'), 'route' => 'wallet.index', 'icon' => 'wallet'],
                ['label' => __('navigation.recharge'), 'route' => 'recharge.index', 'icon' => 'recharge'],
                ['label' => __('navigation.withdraw'), 'route' => 'withdraw.index', 'icon' => 'withdraw'],
            ],
        ],
        [
            'label' => __('navigation.rewards'),
            'items' => [
                ['label' => __('navigation.team'), 'route' => 'my-team.index', 'icon' => 'team'],
                ['label' => __('navigation.salary'), 'route' => 'salary.index', 'icon' => 'salary'],
                ['label' => __('navigation.bonus_code'), 'route' => 'bonus-codes.index', 'icon' => 'bonus'],
            ],
        ],
        [
            'label' => __('navigation.support'),
            'items' => [
                ['label' => __('navigation.contact'), 'route' => 'contact.index', 'icon' => 'contact'],
                ['label' => __('navigation.profile'), 'route' => 'profile.index', 'icon' => 'profile'],
            ],
        ],
    ];

    $user = auth()->user();

    $currentVipLevel = method_exists($user, 'currentVipLevel') ? $user->currentVipLevel() : null;
@endphp

<div
     x-data="{ open: false }"
     x-on:open-mobile-menu.window="open = true"
     x-cloak>
    <div
         x-show="open"
         x-transition.opacity
         class="fixed inset-0 z-50 bg-black/50 xl:hidden"
         @click="open = false"></div>

    <aside
           x-show="open"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           class="fixed right-0 top-0 z-50 h-full w-[88vw] max-w-sm overflow-y-auto bg-[#003b7a] text-white shadow-2xl xl:hidden">
        <div class="sticky top-0 z-10 border-b border-white/10 bg-[#003b7a] px-5 py-4">
            <div class="flex items-center justify-between">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3" @click="open = false">
                    <img
                         src="{{ asset('logo.jpg') }}"
                         alt="{{ __('app.brand_name') }}"
                         class="h-11 w-11 rounded-full object-cover shadow-sm ring-2 ring-[#ffd900] lg:h-12 lg:w-12">

                    <div>
                        <div class="font-black leading-none">
                            {{ __('app.brand_name') }}
                        </div>

                        <div class="mt-1 text-[10px] font-black uppercase tracking-[0.22em] text-[#ffd900]">
                            LV{{ $currentVipLevel?->level ?? 1 }}
                        </div>
                    </div>
                </a>

                <button
                        type="button"
                        class="grid h-10 w-10 place-items-center rounded-full bg-white/10 text-xl transition hover:bg-white/20"
                        @click="open = false"
                        aria-label="{{ __('navigation.close_menu') }}">
                    ×
                </button>
            </div>
        </div>

        <div class="px-5 py-5">
            <a
               href="{{ route('profile.index') }}"
               class="block rounded-[1.5rem] bg-white/10 p-4 ring-1 ring-white/10 transition hover:bg-white/15"
               @click="open = false">
                <div class="flex items-center gap-3">
                    @if ($user->profile_photo_url)
                        <img
                             src="{{ $user->profile_photo_url }}"
                             alt="{{ $user->username }}"
                             class="h-14 w-14 rounded-2xl border-2 border-[#ffd900] object-cover">
                    @else
                        <div
                             class="grid h-14 w-14 place-items-center rounded-2xl border-2 border-[#ffd900] bg-white text-lg font-black uppercase text-[#003b7a]">
                            {{ str($user->username)->substr(0, 2)->upper() }}
                        </div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-lg font-black uppercase">
                            {{ $user->username }}
                        </div>

                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                  class="rounded-full bg-[#ffd900] px-2.5 py-1 text-[10px] font-black uppercase text-[#003b7a]">
                                LV{{ $currentVipLevel?->level ?? 1 }}
                            </span>

                            <span
                                  class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $user->status === 'active' ? 'bg-green-400 text-[#003b7a]' : 'bg-red-500 text-white' }}">
                                {{ $user->status === 'active' ? __('app.active_member') : __('app.inactive_account') }}
                            </span>
                        </div>
                    </div>

                    <div class="text-xl font-black text-white/30">
                        →
                    </div>
                </div>
            </a>

            <nav class="mt-5 space-y-5">
                @foreach ($groups as $group)
                    <div>
                        <div class="mb-2 px-1 text-[10px] font-black uppercase tracking-[0.22em] text-[#ffd900]/80">
                            {{ $group['label'] }}
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach ($group['items'] as $item)
                                @php
                                    $active = request()->routeIs($item['route']);
                                @endphp

                                <a
                                   href="{{ route($item['route']) }}"
                                   class="group rounded-2xl p-3 transition
                                        {{ $active ? 'bg-[#ffd900] text-[#003b7a]' : 'bg-white/10 text-white hover:bg-white/15' }}"
                                   @click="open = false">
                                    <div class="flex items-center justify-between gap-2">
                                        <span
                                              class="grid h-9 w-9 place-items-center rounded-xl
                                            {{ $active ? 'bg-[#003b7a] text-white' : 'bg-white/10 text-white' }}">
                                            @include('partials.icon', [
                                                'name' => $item['icon'],
                                                'class' => 'h-5 w-5',
                                            ])
                                        </span>

                                        <span
                                              class="text-base font-black opacity-30 transition group-hover:translate-x-0.5">
                                            →
                                        </span>
                                    </div>

                                    <div class="mt-3 truncate text-xs font-black uppercase">
                                        {{ $item['label'] }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </nav>
            <div class="mt-6 rounded-[1.5rem] bg-white/10 p-4 ring-1 ring-white/10">
                <div class="mb-3 text-[10px] font-black uppercase tracking-[0.22em] text-[#ffd900]/80">
                    {{ __('app.language') }}
                </div>

                @include('partials.language-switcher')
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf

                <button
                        type="submit"
                        class="w-full rounded-2xl bg-white px-5 py-3 text-sm font-black uppercase text-[#003b7a] shadow-lg">
                    {{ __('app.logout') }}
                </button>
            </form>
        </div>
    </aside>
</div>
