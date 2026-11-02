<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$wizard['title'].' · '.$workshop->name">
    <article class="y-panel y-panel-reservation">
        <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit class="y-panel-close" aria-label="{{ $site['close'] }}"><span aria-hidden="true">×</span></a>

        <div class="y-panel-side" @if ($photo) style="--y-photo: url('{{ $photo }}')" @endif>
            <div class="y-side-logo"><x-site-logo /><span><strong>YOSHIDA AUTOMOTIVE</strong><small>吉田自動車工業</small></span></div>
            <div class="y-side-main">
                <x-site-icon name="calendar" class="y-side-icon" />
                <h1>{{ $wizard['title'] }}</h1>
                <p class="y-side-en">RESERVATION</p>
                <p class="y-side-body">{{ $wizard['intro'] }}</p>
            </div>
            <ul class="y-badges">
                @foreach (['calendar', 'car', 'tools', 'phone', 'check'] as $index => $icon)
                    <li><x-site-icon :name="$icon" /><span>{{ $wizard['steps'][$index] }}</span></li>
                @endforeach
            </ul>
        </div>

        <div class="y-panel-main">
            @include('workshop.reservation')
        </div>
    </article>
</x-workshop-stage>
