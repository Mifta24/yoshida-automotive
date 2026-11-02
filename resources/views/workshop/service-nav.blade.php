{{-- On the services scenes the menu card becomes the service index, so the customer can step between services without going back. --}}
<aside class="stage-menu" aria-label="{{ $labels['services_heading'] }}">
    <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_garage'] }}" class="stage-menu-back"><span aria-hidden="true">‹</span> {{ $garage['explore'] }}</a>
    <p class="garage-eyebrow">{{ $labels['services_heading'] }}</p>
    <nav class="service-nav">
        @foreach ($serviceTypes as $item)
            <a href="{{ route('workshop.service', ['workshopSlug' => $workshop->slug, 'serviceSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($serviceType ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="service-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="service-nav-text">
                    <span class="service-nav-name">{{ $item->translatedName($locale) }}</span>
                    <span class="service-nav-price">{{ $labels['from'] }} {{ $workshop->money($item->base_price) }} {{ $labels['price_suffix'] }}</span>
                </span>
            </a>
        @endforeach
    </nav>
    <span class="stage-menu-footer">{{ $garage['available'] }} · AI CONCIERGE</span>
</aside>
