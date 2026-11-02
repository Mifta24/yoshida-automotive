<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$facility->translatedTitle($locale).' · '.$workshop->name">
    @php
        $title = $facility->translatedTitle($locale);
        $facilityUrl = fn ($item) => route('workshop.facility', ['workshopSlug' => $workshop->slug, 'facilityId' => $item->id, 'lang' => $locale]);
    @endphp

    <div class="stage-panels">
        <section class="garage-content stage-panel-right @container" aria-label="{{ $title }}">
            <a href="{{ route('workshop.facilities', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $garage['back_facilities'] }}"><span aria-hidden="true">×</span></a>
            <article class="service-scene">
                <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
                    <p class="garage-eyebrow">{{ $garage['facility_counter'] }} {{ $facilityIndex + 1 }} / {{ $facilities->count() }}</p>
                    @if ($facilities->count() > 1)
                        <nav class="flex gap-2" aria-label="{{ $labels['menu_facilities'] }}">
                            <a href="{{ $facilityUrl($previousFacility) }}" data-stage-exit class="service-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $garage['prev_facility'] }}</a>
                            <a href="{{ $facilityUrl($nextFacility) }}" data-stage-exit class="service-scene-step" rel="next">{{ $garage['next_facility'] }} <span aria-hidden="true">→</span></a>
                        </nav>
                    @endif
                </div>
                @if ($facility->image_url)
                    <div class="facility-hero mt-5">
                        <img src="{{ $facility->image_url }}" alt="{{ $title }}">
                        <span class="facility-card-icon"><x-facility-icon :name="$facility->iconName()" /></span>
                    </div>
                @endif
                @if (count($facility->photoUrls()) > 1)
                    <div class="facility-gallery mt-3">
                        @foreach (array_slice($facility->photoUrls(), 1) as $photoUrl)
                            <img src="{{ $photoUrl }}" alt="{{ $title }}" loading="lazy">
                        @endforeach
                    </div>
                @endif
                <h2>{{ $title }}</h2>
                @if ($facility->openingHours())
                    <p class="facility-hours">{{ $labels['chat_hours'] }}: {{ $facility->openingHours() }}</p>
                @endif
                @include('workshop.narrator', ['text' => $facility->translatedBody($locale), 'key' => 'facility-'.$facility->id, 'inline' => true])
                @if ($facility->menuEntries() !== [])
                    <ul class="rc-menu facility-menu mt-5">
                        @foreach ($facility->menuEntries() as $dish)
                            <li>
                                <span class="rc-menu-name">{{ $dish['name'] }}</span>
                                @if ($dish['price'])<span class="rc-menu-price">{{ $dish['price'] }}</span>@endif
                                @if ($dish['description'])<span class="rc-menu-desc">{{ $dish['description'] }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="button" class="garage-action" data-hero-quick-message="{{ str_replace(':name', $title, $garage['ask_facility_q']) }}">{{ $garage['ask_facility'] }} <span aria-hidden="true">↗</span></button>
                    <a href="{{ route('workshop.facilities', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit class="service-scene-step">{{ $garage['back_facilities'] }}</a>
                </div>
            </article>
        </section>
    </div>
</x-workshop-stage>
