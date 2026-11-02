<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$labels['menu_staff'].' · '.$workshop->name">
    <div class="stage-panels">
        <section class="garage-content staff-panel @container" aria-label="{{ $labels['menu_staff'] }}">
            <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_garage'] }}" class="panel-close" aria-label="{{ $garage['back'] }}"><span aria-hidden="true">×</span></a>
            <p class="garage-eyebrow">{{ $workshop->name }}</p>
            <h2>{{ $labels['menu_staff'] }}</h2>
            @include('workshop.narrator', ['text' => $garage['staff_intro'], 'key' => 'staff', 'inline' => true])

            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" data-hero-quick-message="{{ $labels['menu_staff_q'] }}" class="garage-action">{{ $labels['menu_staff'] }} <span aria-hidden="true">↗</span></button>
                @if($staffLinks['whatsapp_url'])<a href="{{ $staffLinks['whatsapp_url'] }}" target="_blank" rel="noopener" class="wizard-secondary">{{ $wizard['whatsapp'] }}</a>@endif
                @if($staffLinks['phone_url'])<a href="{{ $staffLinks['phone_url'] }}" class="wizard-secondary">{{ $wizard['call_workshop'] }}</a>@endif
                <a href="{{ route('workshop.booking', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" class="wizard-secondary">{{ \App\Http\Controllers\CustomerBookingController::LABELS[$locale]['title'] }}</a>
                @if($workshop->supportsMembers())<a href="{{ route('member.account', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" class="wizard-secondary">{{ \App\Core\Member\Support\MemberLabels::for($locale)['member_link'] }}</a>@endif
                @if($staffLinks['email_url'])<a href="{{ $staffLinks['email_url'] }}" class="wizard-secondary">{{ $wizard['email_workshop'] }}</a>@endif
            </div>

            <div class="staff-location">
                <h3>{{ $garage['location'] }}</h3>
                <p>{{ $workshop->address }}, {{ $workshop->city }}, {{ $workshop->country }}</p>
                @if($workshop->phone)<p>{{ $workshop->phone }}</p>@endif
                @if($workshop->email)<p>{{ $workshop->email }}</p>@endif
            </div>
        </section>
    </div>
</x-workshop-stage>
