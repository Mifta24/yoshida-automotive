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
        @case('wheel')
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 3v6.5M12 14.5V21M3.5 8l5.7 3.3M14.8 12.7l5.7 3.3M3.5 16l5.7-3.3M14.8 11.3 20.5 8"/>
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16M8 3v4M16 3v4M8 14h2M12 14h2M8 17.5h2"/>
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
        @case('robot')
            <rect x="5" y="8" width="14" height="11" rx="3.5"/><path d="M12 8V5M12 4.5h.01M9 13h.01M15 13h.01M9.5 16.5h5M3 12.5v3.5M21 12.5v3.5"/>
            @break
        @case('diamond')
            <path d="M6 4h12l4 6-10 11L2 10l4-6Z"/><path d="M2 10h20M9 4l-2 6 5 11 5-11-2-6"/>
            @break
        @case('engine')
            <path d="M4 9h3l1-2h6l1 2h3v3h2v4h-2v2H8l-2-2H4V9Z"/><path d="M10 12.5h4"/>
            @break
        @case('clipboard')
            <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9zM8 11h8M8 15h5"/>
            @break
        @case('oil')
            <path d="M3 11h6l3-4h6l3 3-4 2v4H8l-5-5Z"/><path d="M19 17.5c.8 1.2 1.2 2 1.2 2.6a1.2 1.2 0 0 1-2.4 0c0-.6.4-1.4 1.2-2.6Z"/>
            @break
        @case('battery')
            <rect x="3" y="8" width="18" height="12" rx="2"/><path d="M7 8V5.5h3V8M14 8V5.5h3V8M7.5 14h4M9.5 12v4M14.5 14h2"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="6"/><path d="m20 20-4.2-4.2"/>
            @break
        @case('doc')
            <path d="M6 3h9l4 4v14H6V3Z"/><path d="M15 3v4h4M9 12h7M9 16h7"/>
            @break
        @case('handshake')
            <path d="m3 12 4-4 4 2 3-2 5 4v3l-4 4-3-1-2 1-4-3-3-4Z"/><path d="m9 12 3 3"/>
            @break
        @case('coins')
            <ellipse cx="10" cy="7" rx="6" ry="3"/><path d="M4 7v5c0 1.7 2.7 3 6 3M4 12v5c0 1.7 2.7 3 6 3M16 9.5c2.4.3 4 1.3 4 2.5v5c0 1.7-2.7 3-6 3"/>
            @break
        @case('headset')
            <path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19c0 1.5-2 2-5 2"/>
            @break
        @case('check')
            <circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>
            @break
        @case('warehouse')
            <path d="M3 21V9l9-5 9 5v12"/><path d="M7 21v-7h10v7M7 17.5h10"/>
            @break
        @case('person')
            <circle cx="10" cy="8" r="3.5"/><path d="M3 20a7 7 0 0 1 14 0M19 8v6M16 11h6"/>
            @break
        @case('gear')
            <circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/>
            @break
        @case('crown')
            <path d="m3 8 4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8Z"/>
            @break
        @case('leaf')
            <path d="M5 19c0-8 5-14 15-14 0 10-6 15-14 15"/><path d="M5 19c3-5 6-8 10-10"/>
            @break
        @case('snow')
            <path d="M12 2v20M3.3 7l17.4 10M3.3 17 20.7 7M9 4l3 2 3-2M9 20l3-2 3 2"/>
            @break
        @default
            <path d="M4 16v-3l2-5a2 2 0 0 1 2-1.3h8A2 2 0 0 1 18 8l2 5v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1ZM4.5 13h15"/><circle cx="7.5" cy="17" r="1.8"/><circle cx="16.5" cy="17" r="1.8"/>
    @endswitch
</svg>
