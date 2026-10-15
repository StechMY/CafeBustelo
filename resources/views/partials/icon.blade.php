@props(['name', 'class' => 'h-5 w-5'])

@if ($name === 'golden-egg')
    <img src="{{ asset('images/golden-egg/vault-egg-icon.webp') }}" alt="" width="96" height="128" {{ $attributes->merge(['class' => $class.' object-contain']) }} aria-hidden="true">
@else
@php
    $icons = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',

        'products' => '<path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/><path d="M9 13h6"/>',

        'recharge' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18"/><path d="M7 15h4"/>',

        'withdraw' =>
            '<path d="M4 10h16"/><path d="M6 10V7l6-3 6 3v3"/><path d="M7 10v8"/><path d="M12 10v8"/><path d="M17 10v8"/><path d="M5 18h14"/>',

        'team' =>
            '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',

        'wallet' =>
            '<path d="M4 7h15a2 2 0 0 1 2 2v10H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h13"/><path d="M16 13h5"/><circle cx="17.5" cy="13" r=".5"/>',

        'bonus' =>
            '<path d="M20 12v8H4v-8"/><path d="M22 7H2v5h20V7Z"/><path d="M12 22V7"/><path d="M12 7H7.5A2.5 2.5 0 1 1 10 4.5C10 6 12 7 12 7Z"/><path d="M12 7h4.5A2.5 2.5 0 1 0 14 4.5C14 6 12 7 12 7Z"/>',

        'salary' => '<path d="M12 2v20"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/>',

        'menu' => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',

        'logout' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 3v18"/>',
        'contact' =>
            '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z"/><path d="M8 9h8"/><path d="M8 13h5"/>',

        'whatsapp' =>
            '<path d="M3 21l1.6-4.7A8.5 8.5 0 1 1 8 19.4L3 21Z"/><path d="M9 8.5c.4 2 2 3.7 4 4.5l1.2-1.2c.2-.2.5-.3.8-.2 1 .3 1.8.5 2.5.5.3 0 .5.2.5.5V15c0 .3-.2.5-.5.5A9.5 9.5 0 0 1 8.5 6c0-.3.2-.5.5-.5h2.4c.3 0 .5.2.5.5 0 .8.2 1.7.5 2.5.1.3 0 .6-.2.8L11 10.5"/>',

        'telegram' => '<path d="M21 4 3 11l7 2 2 7 9-16Z"/><path d="m10 13 4-4"/>',

        'email' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',

        'phone' =>
            '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.7.6 2.5a2 2 0 0 1-.5 2.1L8 9.5a16 16 0 0 0 6.5 6.5l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.6.5 2.5.6a2 2 0 0 1 1.7 2Z"/>',

        'link' =>
            '<path d="M10 13a5 5 0 0 0 7.1 0l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"/><path d="M14 11a5 5 0 0 0-7.1 0l-2 2a5 5 0 0 0 7.1 7.1l1.1-1.1"/>',
        'check-in' => '<path d="M20 6 9 17l-5-5"/><path d="M21 12a9 9 0 1 1-3-6.7"/>',

        'speaker' =>
            '<path d="M4 9v6h4l5 4V5L8 9H4Z"/><path d="M16 8.5a4 4 0 0 1 0 7"/><path d="M18.5 6a7 7 0 0 1 0 12"/>',

        'profile' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        
        'calculator' =>
            '<path d="M7 2h10a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M8 6h8"/><path d="M8 10h2"/><path d="M12 10h2"/><path d="M16 10h0"/><path d="M8 14h2"/><path d="M12 14h2"/><path d="M16 14h0"/><path d="M8 18h2"/><path d="M12 18h2"/><path d="M16 18h0"/>',
            'zalo' => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 5.86 2 10.62c0 2.75 1.5 5.2 3.84 6.78l-.72 3.04c-.08.34.28.61.58.43l3.22-1.9c.96.18 1.99.28 3.08.28 5.52 0 10-3.86 10-8.63S17.52 2 12 2Zm-4.88 6.9h3.72c.32 0 .58.26.58.58 0 .14-.05.28-.15.39l-2.6 3.12h2.2c.32 0 .58.26.58.58s-.26.58-.58.58H7.05a.58.58 0 0 1-.44-.96l2.63-3.13H7.12a.58.58 0 1 1 0-1.16Zm6.44 5.25a2.27 2.27 0 0 1-2.28-2.32 2.27 2.27 0 0 1 2.28-2.32c.57 0 1.02.18 1.36.51v-.05c0-.28.23-.51.51-.51s.51.23.51.51v3.64c0 .28-.23.51-.51.51s-.51-.23-.51-.51v-.05c-.34.36-.79.59-1.36.59Zm.18-1.03c.7 0 1.18-.54 1.18-1.29s-.48-1.29-1.18-1.29-1.18.54-1.18 1.29.48 1.29 1.18 1.29Zm3.39-3.75c0-.3.24-.54.54-.54s.54.24.54.54v4.21c0 .3-.24.54-.54.54s-.54-.24-.54-.54V9.37Z"/>
    </svg>',
    'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14a2 2 0 0 0 2-2v-3"/><path d="M3 16v3a2 2 0 0 0 2 2"/>',
    ];

    $svg = $icons[$name] ?? $icons['home'];
@endphp

<svg
     {{ $attributes->merge(['class' => $class]) }}
     viewBox="0 0 24 24"
     fill="none"
     stroke="currentColor"
     stroke-width="2"
     stroke-linecap="round"
     stroke-linejoin="round"
     aria-hidden="true">
    {!! $svg !!}
</svg>
@endif
