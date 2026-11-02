@php
    $about = $workshop->translatedDescription($locale);
    $mapUrl = $workshop->latitude && $workshop->longitude
        ? 'https://www.google.com/maps?q='.$workshop->latitude.','.$workshop->longitude
        : 'https://www.google.com/maps?q='.rawurlencode(trim($workshop->address.' '.$workshop->city));
    $sections = collect([
        'general' => ['tab' => $garage['info_tab_about'], 'heading' => $garage['info_about']],
        'policies' => ['tab' => $garage['info_policies'], 'heading' => $garage['info_policies']],
        'faq' => ['tab' => $garage['info_tab_faq'], 'heading' => $garage['info_faq']],
    ])->filter(fn ($section, $category) => ($infoItems[$category] ?? collect())->isNotEmpty());
@endphp
<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$garage['menu_info'].' · '.$workshop->name">
    <div class="stage-panels">
        <section class="garage-content info-panel @container" aria-label="{{ $garage['menu_info'] }}">
            <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_garage'] }}" class="panel-close" aria-label="{{ $garage['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="garage-eyebrow">{{ $workshop->name }}</p>
            <h2>{{ $garage['menu_info'] }}</h2>
            @include('workshop.narrator', ['text' => $sceneNarrations['info'], 'key' => 'info', 'inline' => true])

            @if ($about)
                <p class="mt-4 leading-relaxed text-stone-600">{{ $about }}</p>
            @endif

            {{-- Drop-off / pickup, read like the two halves of a keycard sleeve. --}}
            <div class="info-visit">
                <div class="info-visit-item">
                    <span class="info-visit-label">{{ $garage['drop_off_date'] }}</span>
                    <span class="info-visit-time">{{ substr($workshop->opening_time ?? '', 0, 5) ?: '—' }}</span>
                </div>
                <div class="info-visit-divider" aria-hidden="true"></div>
                <div class="info-visit-item">
                    <span class="info-visit-label">{{ $garage['pickup_date'] }}</span>
                    <span class="info-visit-time">{{ substr($workshop->closing_time ?? '', 0, 5) ?: '—' }}</span>
                </div>
            </div>

            <div class="info-facts">
                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="info-fact">
                    <x-info-icon name="place" />
                    <span>{{ $workshop->city ?: $garage['info_address'] }}</span>
                </a>
                @if ($staffLinks['whatsapp_url'])
                    <a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="info-fact"><x-info-icon name="contact" /><span>{{ $wizard['whatsapp'] }}</span></a>
                @elseif ($workshop->phone)
                    <a href="{{ $staffLinks['phone_url'] }}" class="info-fact"><x-info-icon name="contact" /><span>{{ $workshop->phone }}</span></a>
                @endif
            </div>

            <p class="info-address">{{ $workshop->address }}, {{ $workshop->city }}, {{ $workshop->country }}</p>

            @if ($sections->count() > 1)
                <div class="info-tabs">
                    @foreach ($sections as $category => $section)
                        <input type="radio" name="info-tab" id="info-tab-{{ $category }}" class="info-tab-radio" @checked($loop->first)>
                    @endforeach

                    <div class="info-tablist">
                        @foreach ($sections as $category => $section)
                            <label for="info-tab-{{ $category }}">{{ $section['tab'] }}</label>
                        @endforeach
                    </div>

                    <div class="info-tabpanels">
                        @foreach ($sections as $category => $section)
                            <div class="info-tabpanel" data-panel="{{ $category }}">
                                <div class="info-list">
                                    @foreach ($infoItems[$category] as $item)
                                        <details class="info-row">
                                            <summary><span>{{ $item->translatedTitle($locale) }}</span><span aria-hidden="true" class="info-row-chevron">›</span></summary>
                                            <p>{{ $item->translatedBody($locale) }}</p>
                                        </details>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif ($sections->isNotEmpty())
                @php $only = $sections->keys()->first(); @endphp
                <h3 class="info-section-heading">{{ $sections[$only]['heading'] }}</h3>
                <div class="info-list">
                    @foreach ($infoItems[$only] as $item)
                        <details class="info-row">
                            <summary><span>{{ $item->translatedTitle($locale) }}</span><span aria-hidden="true" class="info-row-chevron">›</span></summary>
                            <p>{{ $item->translatedBody($locale) }}</p>
                        </details>
                    @endforeach
                </div>
            @endif

            <button type="button" data-hero-quick-message="{{ $labels['menu_policies_q'] }}" class="garage-action mt-8">{{ $garage['info_ask'] }} <span aria-hidden="true">↗</span></button>
        </section>
    </div>
</x-workshop-stage>
