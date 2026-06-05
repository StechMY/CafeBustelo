@php
    $variant = $variant ?? 'desktop';

    $supportedLocales = config('app.supported_locales', [
        'en' => [
            'label' => 'English',
            'short' => 'EN',
            'flag' => '/flags/en.svg',
        ],
        'zh_CN' => [
            'label' => '中文',
            'short' => '中文',
            'flag' => '/flags/zh_CN.svg',
        ],
        'id' => [
            'label' => 'Indonesia',
            'short' => 'ID',
            'flag' => '/flags/id.svg',
        ],
        'vi' => [
            'label' => 'Tiếng Việt',
            'short' => 'VI',
            'flag' => '/flags/vi.svg',
        ],
    ]);

    $currentLocale = app()->getLocale();
    $currentLanguage = $supportedLocales[$currentLocale] ?? $supportedLocales['en'];

    $isMobile = $variant === 'mobile';
@endphp

<div
    x-data="{ open: false }"
    class="relative {{ $isMobile ? 'block w-full' : 'inline-flex' }}"
    @click.outside="open = false"
>
    <button
        type="button"
        class="{{ $isMobile ? 'flex h-12 w-full justify-between rounded-2xl px-4' : 'inline-flex h-10 rounded-full px-3' }}
               items-center gap-2 bg-white/10 text-xs font-black uppercase text-white ring-1 ring-white/10 transition hover:bg-white/15"
        @click="open = ! open"
    >
        <span class="flex min-w-0 items-center gap-2">
            @if (! empty($currentLanguage['flag']))
                <img
                    src="{{ asset(ltrim($currentLanguage['flag'], '/')) }}"
                    alt="{{ $currentLanguage['short'] ?? $currentLocale }}"
                    class="h-4 w-5 shrink-0 rounded-sm object-cover"
                >
            @endif

            <span class="truncate">
                {{ $isMobile ? ($currentLanguage['label'] ?? $currentLocale) : ($currentLanguage['short'] ?? $currentLocale) }}
            </span>
        </span>

        <svg
            class="h-3 w-3 shrink-0 opacity-70 transition"
            :class="{ 'rotate-180': open }"
            viewBox="0 0 20 20"
            fill="currentColor"
        >
            <path
                fill-rule="evenodd"
                d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z"
                clip-rule="evenodd"
            />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="{{ $isMobile
            ? 'relative mt-2 w-full rounded-2xl'
            : 'absolute right-0 top-12 z-50 w-48 rounded-2xl'
        }} overflow-hidden bg-white py-2 text-[#003b7a] shadow-2xl ring-1 ring-black/5"
    >
        @foreach ($supportedLocales as $locale => $language)
            @php
                $label = is_array($language) ? ($language['label'] ?? $locale) : $language;
                $short = is_array($language) ? ($language['short'] ?? $label) : $language;
                $flag = is_array($language) ? ($language['flag'] ?? '') : '';
            @endphp

            <form method="POST" action="{{ route('locale.update') }}">
                @csrf

                <input type="hidden" name="locale" value="{{ $locale }}">

                <button
                    type="submit"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm font-black transition hover:bg-[#fff8c7]"
                >
                    <span class="flex min-w-0 items-center gap-3">
                        @if ($flag)
                            <img
                                src="{{ asset(ltrim($flag, '/')) }}"
                                alt="{{ $short }}"
                                class="h-4 w-5 shrink-0 rounded-sm object-cover"
                            >
                        @endif

                        <span class="truncate">{{ $label }}</span>
                    </span>

                    <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-black {{ $currentLocale === $locale ? 'bg-[#ffd900] text-[#003b7a]' : 'bg-[#003b7a]/10 text-[#003b7a]/60' }}">
                        {{ $short }}
                    </span>
                </button>
            </form>
        @endforeach
    </div>
</div>