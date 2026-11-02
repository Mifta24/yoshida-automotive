@props(['workshop', 'site', 'locale', 'supportedLocales', 'labels', 'garage', 'narration', 'scene', 'backdrop', 'menuItems', 'wizard', 'serviceTerms', 'today', 'tourStops', 'prefill' => [], 'title' => null, 'welcome' => null, 'sidebar' => null, 'menu' => true])
{{-- The fixed full-screen stage every scene shares: backdrop, header, menu and the advisor chat dock. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $workshop->name.' · AI Concierge' }}</title>
    <meta property="og:title" content="{{ $workshop->name }}">
    @if ($workshop->coverUrl())<meta property="og:image" content="{{ $workshop->coverUrl() }}">@endif
    @if (filled($workshop->description))<meta property="og:description" content="{{ \Illuminate\Support\Str::limit($workshop->description, 200) }}">@endif
    <script>try { document.documentElement.dataset.theme = localStorage.getItem('yoshida-theme') || 'light'; } catch { document.documentElement.dataset.theme = 'light'; }</script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--advisor-image: url('{{ $backdrop['avatarImage'] }}'); --stage-focus: {{ $backdrop['focus'] }}; --stage-focus-mobile: {{ $backdrop['focusMobile'] }}; --advisor-zoom: {{ $backdrop['avatarZoom'] }}; --advisor-focus: {{ $backdrop['avatarFocus'] }}">
    <main class="stage" data-garage data-scene="{{ $scene }}" data-page="{{ $scene === 'garage' ? 'home' : $scene }}" data-chat-dock="{{ $scene === 'info' ? 'center' : ($scene === 'staff' ? 'left' : 'right') }}">
        <div class="stage-loader" data-stage-loader role="status"><span class="workshop-monogram" aria-hidden="true">@if ($workshop->logoUrl())<img src="{{ $workshop->logoUrl() }}" alt="">@else{{ mb_substr($workshop->name, 0, 1) }}@endif</span><p>{{ $garage['loading'] }}</p></div>
        <img src="{{ $backdrop['image'] }}" alt="{{ $backdrop['character'] ? '' : $garage['assistant'] }}" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>
        @if ($backdrop['character'])
            <img src="{{ $backdrop['character'] }}" alt="{{ $garage['assistant'] }}" class="stage-character" data-anchor="{{ $backdrop['anchor'] }}">
        @endif
        <p class="stage-tour" data-stage-tour aria-live="polite"></p>

        <header class="stage-header y-header">
            <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" @if($scene !== 'garage') data-stage-exit @endif class="y-logo">
                <span class="workshop-monogram" aria-hidden="true">@if ($workshop->logoUrl())<img src="{{ $workshop->logoUrl() }}" alt="">@else<x-site-icon name="car" />@endif</span>
                <span class="min-w-0"><strong>{{ \Illuminate\Support\Str::upper($workshop->name) }}</strong>@if (filled($workshop->translations['ja']['name'] ?? null))<small lang="ja">{{ $workshop->translations['ja']['name'] }}</small>@endif</span>
            </a>
            <nav class="y-nav" aria-label="{{ $site['nav_services'] }}">
                <details class="y-nav-menu">
                    <summary>{{ $site['nav_services'] }}</summary>
                    <ul>
                        @foreach (\App\Services\WorkshopDivisions::cards($locale) as $card)
                            <li><a href="{{ route('workshop.division', ['workshopSlug' => $workshop->slug, 'division' => $card['key'], 'lang' => $locale]) }}" data-stage-exit>{{ $card['title'] }}</a></li>
                        @endforeach
                    </ul>
                </details>
                <a href="{{ route('workshop.division', ['workshopSlug' => $workshop->slug, 'division' => 'body-paint', 'tab' => 1, 'lang' => $locale]) }}" data-stage-exit>{{ $site['nav_cases'] }}</a>
                <a href="{{ route('workshop.company', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit>{{ $site['nav_company'] }}</a>
                <a href="{{ route('workshop.info', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit>{{ $site['nav_access'] }}</a>
            </nav>
            <div class="stage-tools">
                <button type="button" class="y-theme" data-theme-toggle data-label-light="{{ $site['theme_light'] }}" data-label-dark="{{ $site['theme_dark'] }}"><x-site-icon name="moon" class="y-theme-moon" /><x-site-icon name="sun" class="y-theme-sun" /></button>
                <x-sound-toggle :on="$garage['sound_on']" :off="$garage['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
                <a class="y-header-cta" href="{{ route('workshop.reservation', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" @if($scene !== 'reservation') data-stage-exit @endif><x-site-icon name="chat" /> {{ $site['nav_ai'] }}</a>
            </div>
        </header>

        {{ $welcome }}

@if(! $menu)
@elseif($sidebar)
        {{ $sidebar }}
@else
        <aside class="stage-menu" aria-label="{{ $garage['explore'] }}">
            <p class="garage-eyebrow">{{ $garage['explore'] }}</p>
            <nav class="garage-navigation">
                @foreach ($menuItems as $item)
                    <a href="{{ $item['href'] }}"
                        @if($item['exit']) data-stage-exit @if($item['tour']) data-tour-line="{{ $item['tour'] }}" @endif data-topic="{{ $item['topic'] }}" @else data-garage-link="{{ $item['key'] }}" aria-controls="garage-{{ $item['key'] }}" @endif
                        @if($item['key'] === $scene || ($scene === 'service' && $item['key'] === 'services')) aria-current="page" @endif>
                        <span>{{ $item['label'] }}</span><span class="nav-arrow" aria-hidden="true">›</span>
                    </a>
                @endforeach
            </nav>
            <span class="stage-menu-footer">{{ $garage['available'] }} · AI CONCIERGE</span>
        </aside>
@endif

        {{ $slot }}

        <div data-chat-widget>
            @include('workshop.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Advisor. {{ $workshop->phone }}</p></noscript>
    </main>
</body>
</html>
