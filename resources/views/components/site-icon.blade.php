@props(['name' => 'car'])
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('tools')
            <path d="M14.7 6.3a4 4 0 0 1 5-1.1l-2.8 2.8 2.1 2.1 2.8-2.8a4 4 0 0 1-5.3 5.3L8 20.1a2 2 0 0 1-2.8-2.8l7.5-7.5a4 4 0 0 1 2-3.5Z"/>
            @break
        @case('shield')
            <path d="M12 3 5 6v5c0 4.5 3 8.2 7 10 4-1.8 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('gavel')
            <path d="m14 4 6 6M11 7l6 6M12.5 5.5l-5 5 6 6 5-5M9 13l-6 6 2 2 6-6M13 21h8"/>
            @break
        @case('tire')
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M12 3v5.5M12 15.5V21M3 12h5.5M15.5 12H21"/>
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/>
            @break
        @case('chat')
            <path d="M4 5h16v11H9l-5 4V5Z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/>
            @break
        @case('phone')
            <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2Z"/>
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5 19 19M19 5l-1.5 1.5M6.5 17.5 5 19"/>
            @break
        @case('moon')
            <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5Z"/>
            @break
        @default
            <path d="M4 16v-3l2-5a2 2 0 0 1 2-1.3h8A2 2 0 0 1 18 8l2 5v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1ZM4.5 13h15"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/>
    @endswitch
</svg>
