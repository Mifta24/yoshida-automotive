<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased">
    <main class="stage">
        <div class="stage-loader" data-stage-loader role="status"><span class="workshop-monogram" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span><p>{{ $opening['loading'] }}</p></div>
        <img src="{{ asset('images/workshop-backdrop.svg') }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>

        <header class="stage-header">
            <span class="flex min-w-0 items-center gap-3">
                <span class="workshop-monogram" aria-hidden="true">{{ mb_substr(config('app.name'), 0, 1) }}</span>
                <span class="block truncate font-semibold tracking-tight">{{ config('app.name') }}</span>
            </span>
            <div class="stage-tools">
                <x-sound-toggle :on="$opening['sound_on']" :off="$opening['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        <section class="opening-panel" aria-labelledby="opening-title">
            <p class="garage-eyebrow">AI CONCIERGE</p>
            <h1 id="opening-title">{{ $opening['welcome'] }}<br><em>{{ config('app.name') }}</em></h1>
            <p class="opening-tagline">{{ $opening['tagline'] }}</p>
        </section>

        <div class="opening-enter">
            @forelse ($workshops as $workshop)
                <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" class="opening-button" data-stage-exit>
                    {{ str_replace(':name', $workshop->name, $opening['enter']) }} <span aria-hidden="true">→</span>
                </a>
            @empty
                <p class="opening-empty">{{ $opening['empty'] }}</p>
            @endforelse
        </div>
    </main>
</body>
</html>
