@php
    $supportedLocales = config('app.supported_locales', [
        'en' => 'English',
        'zh_CN' => '中文',
    ]);

    $currentLocale = app()->getLocale();
@endphp

<form method="POST" action="{{ route('locale.update') }}" class="inline-flex">
    @csrf

    <select
        name="locale"
        onchange="this.form.submit()"
        class="rounded-full border-0 bg-white/10 px-3 py-2 text-xs font-black uppercase text-white outline-none ring-1 ring-white/10 transition hover:bg-white/15"
    >
        @foreach ($supportedLocales as $locale => $label)
            <option
                value="{{ $locale }}"
                @selected($currentLocale === $locale)
                class="text-[#003b7a]"
            >
                {{ $label }}
            </option>
        @endforeach
    </select>
</form>