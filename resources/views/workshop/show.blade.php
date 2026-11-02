<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false">
    <div class="y-home">
        <section class="y-hero">
            <div class="y-hero-copy">
                <h1>{{ $site['hero_line_1'] }}<br>{{ $site['hero_line_2'] }}</h1>
                <p>{{ $site['hero_sub'] }}</p>
                <div class="y-hero-actions">
                    <button type="button" class="y-btn y-btn-gold" data-hero-quick-message="{{ $site['hero_ai'] }}"><x-site-icon name="chat" /> {{ $site['hero_ai'] }} <span aria-hidden="true">›</span></button>
                    <a href="#y-cards" class="y-btn y-btn-ghost">{{ $site['hero_services'] }} <span aria-hidden="true">›</span></a>
                </div>
            </div>

            <aside class="y-ai-panel" aria-label="{{ $site['panel_title'] }}">
                <header><x-site-icon name="robot" class="y-ai-head-icon" /><strong>{{ $site['panel_title'] }}</strong><span><i></i>{{ $site['panel_online'] }}</span></header>
                <div class="y-ai-msg"><span class="y-ai-bot"><x-site-icon name="robot" /></span><p class="y-ai-bubble">{{ $site['panel_greeting'] }}</p></div>
                @foreach (['quick_repair' => 'tools', 'quick_inspection' => 'calendar', 'quick_tire' => 'tire'] as $key => $icon)
                    <button type="button" class="y-ai-quick" data-hero-quick-message="{{ $site[$key] }}"><x-site-icon :name="$icon" /> {{ $site[$key] }} <span aria-hidden="true">›</span></button>
                @endforeach
                <form class="y-ai-form" data-site-ask>
                    <input type="text" maxlength="4000" aria-label="{{ $site['panel_placeholder'] }}" placeholder="{{ $site['panel_placeholder'] }}" autocomplete="off">
                    <button type="submit" aria-label="{{ $labels['chat_send'] }}"><span aria-hidden="true">➤</span></button>
                </form>
            </aside>
        </section>

        <nav id="y-cards" class="y-cards" aria-label="{{ $site['nav_services'] }}">
            @foreach ($divisionCards as $card)
                <a class="y-card" href="{{ route('workshop.division', ['workshopSlug' => $workshop->slug, 'division' => $card['key'], 'lang' => $locale]) }}" data-stage-exit @if ($divisionImages[$card['key']]) style="--y-photo: url('{{ $divisionImages[$card['key']] }}')" @endif>
                    <x-site-icon :name="$card['icon']" class="y-card-icon" />
                    <strong>{{ $card['title'] }}</strong>
                    <span>{!! nl2br(e($card['text'])) !!}</span>
                    <i aria-hidden="true">›</i>
                </a>
            @endforeach
        </nav>
    </div>
</x-workshop-stage>
