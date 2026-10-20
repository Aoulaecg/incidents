@props(['name'])
<svg {{ $attributes->merge(['class' => 'icon']) }} xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>
            @break
        @case('parking')
            <rect x="3" y="3" width="18" height="18" rx="4"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18m-12 4h2m4 0h2m-8 4h2"/>
            @break
        @case('alert')
            <path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v5m0 3h.01"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2m1-15a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>
            @break
        @case('filter')
            <path d="M4 5h16l-6 7v6l-4 2v-8L4 5Z"/>
            @break
        @case('edit')
            <path d="m14 5 5 5M4 20l5-1L21 7a2 2 0 0 0-4-4L5 15l-1 5Z"/>
            @break
        @case('pause')
            <circle cx="12" cy="12" r="9"/><path d="M9 8v8m6-8v8"/>
            @break
        @case('play')
            <circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4V8Z"/>
            @break
        @case('key')
            <circle cx="8" cy="8" r="5"/><path d="m12 12 9 9m-4-4 3-3m-6 0 3-3"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="3.5"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>
            @break
        @case('lock')
            <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/>
            @break
        @case('eye')
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.6 5.1A12 12 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.2M6.2 6.2A18 18 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.8M10 10a3 3 0 0 0 4 4"/>
            @break
        @case('arrow')
            <path d="M5 12h14m-5-5 5 5-5 5"/>
            @break
        @case('shield')
            <path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>
            @break
        @case('journal')
            <rect x="5" y="3" width="15" height="18" rx="2"/><path d="M9 3v18M12.5 8H17m-4.5 4H17m-4.5 4H16M3 7h3M3 12h3M3 17h3"/>
            @break
        @case('logout')
            <path d="M9 4H5v16h4m1-8h11m-4-4 4 4-4 4"/>
            @break
        @case('check')
            <path d="m5 12 4 4L19 6"/>
            @break
    @endswitch
</svg>
