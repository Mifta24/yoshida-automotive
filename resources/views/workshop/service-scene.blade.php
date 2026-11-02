@php
    $serviceName = $serviceType->translatedName($locale);
    $primaryImage = $serviceType->images->first();
    $serviceUrl = fn ($service) => route('workshop.service', ['workshopSlug' => $workshop->slug, 'serviceSlug' => $service->slug, 'lang' => $locale]);
@endphp
<section class="garage-content @container" aria-label="{{ $serviceName }}">
    <a href="{{ route('workshop.services', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $labels['services_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="service-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="garage-eyebrow">{{ $garage['vehicle_counter'] }} {{ $serviceIndex + 1 }} / {{ $serviceTypes->count() }}</p>
            @if ($serviceTypes->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $garage['service_scene'] }}">
                    <a href="{{ $serviceUrl($previousService) }}" data-stage-exit class="service-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $garage['prev_service'] }}</a>
                    <a href="{{ $serviceUrl($nextService) }}" data-stage-exit class="service-scene-step" rel="next">{{ $garage['next_service'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        @include('workshop.narrator', ['text' => $serviceNarrations['service'][$serviceType->slug], 'key' => 'service-'.$serviceType->slug])

        <div class="mt-5 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-service-gallery>
                <div class="aspect-[4/3] w-full overflow-hidden rounded-2xl bg-stone-200" data-service-photo>
                    @if ($primaryImage)
                        <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" class="h-full w-full object-cover" data-service-gallery-main>
                    @else
                        <div class="grid h-full place-items-center text-sm text-stone-500">{{ $garage['no_photo'] }}</div>
                    @endif
                </div>
                @if ($serviceType->images->count() > 1)
                    <ul class="mt-3 grid grid-cols-4 gap-2" aria-label="{{ $garage['gallery'] }}">
                        @foreach ($serviceType->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="service-scene-thumb" data-service-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text }}" aria-label="{{ $garage['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                    <img src="{{ $image->image_source }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $serviceName }}</h2>
                <p class="mt-3 leading-relaxed text-stone-600">{{ $serviceType->translatedDescription($locale) }}</p>

                <p class="mt-5 text-sm text-stone-500">
                    {{ $labels['from'] }}
                    <span class="text-2xl font-semibold text-stone-900">{{ $workshop->money($serviceType->base_price) }}</span>
                    {{ $labels['price_suffix'] }}
                </p>
                <p class="mt-1 text-xs text-stone-500">{{ $garage['availability_note'] }}</p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('workshop.reservation', ['workshopSlug' => $workshop->slug, 'lang' => $locale, 'service' => $serviceType->slug]) }}" data-stage-exit data-tour-line="{{ $narration['tour_reservation'] }}" class="garage-action">{{ $garage['reserve_service'] }} <span aria-hidden="true">↗</span></a>
                    <button type="button" class="service-scene-step" data-hero-quick-message="{{ str_replace(':name', $serviceName, $garage['ask_service_q']) }}">{{ $garage['ask_service'] }}</button>
                </div>

                <dl class="service-scene-facts">
                    @if ($serviceType->duration_minutes)
                        <div><dt>{{ $garage['duration'] }}</dt><dd>{{ $serviceType->duration_minutes }} {{ $garage['minutes'] }}</dd></div>
                    @endif
                    @if ($serviceType->vehicle_class)
                        <div><dt>{{ $garage['vehicle_class'] }}</dt><dd>{{ $serviceTerms['vehicle_class'][$serviceType->vehicle_class] ?? Str::headline($serviceType->vehicle_class) }}</dd></div>
                    @endif
                    <div><dt>{{ $garage['warranty'] }}</dt><dd>{{ $serviceType->warranty_included ? $labels['warranty_included'] : $garage['warranty_excluded'] }}</dd></div>
                    <div>
                        <dt>{{ $garage['towing'] }}</dt>
                        <dd>
                            @if ($serviceType->towing_available)
                                {{ $garage['towing_yes'] }}@if ($serviceType->towing_price > 0) (+{{ $workshop->money($serviceType->towing_price) }})@endif
                            @else
                                {{ $garage['towing_no'] }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($serviceType->inclusions))
                    <h3 class="mt-6 text-sm font-semibold">{{ $garage['inclusions'] }}</h3>
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($serviceType->inclusions as $inclusion)
                            <li class="rounded-full border border-stone-300 bg-white px-3 py-1 text-xs text-stone-600">{{ $serviceTerms['inclusion'][$inclusion] ?? Str::headline($inclusion) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>
</section>
