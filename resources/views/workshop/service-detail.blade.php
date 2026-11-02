<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :title="$serviceType->translatedName($locale).' · '.$workshop->name">
    @if($serviceTypes->isNotEmpty())
        <x-slot:sidebar>@include('workshop.service-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('workshop.service-scene')
    </div>
</x-workshop-stage>
