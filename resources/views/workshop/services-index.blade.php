<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :title="$labels['services_heading'].' · '.$workshop->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-scene" data-anchor="{{ $backdrop['text'] }}">
            <span class="garage-eyebrow">{{ $garage['explore'] }}</span>
            <h1>{{ $labels['services_heading'] }}</h1>
            @if ($serviceNarrations['services'])
                @include('workshop.narrator', ['text' => $serviceNarrations['services'], 'key' => 'services', 'onStage' => true])
            @else
                <p>{{ $garage['services_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($serviceTypes->isNotEmpty())
        <x-slot:sidebar>@include('workshop.service-nav')</x-slot:sidebar>
    @endif
</x-workshop-stage>
