<h2 class="y-heading">{{ $division['items_heading'] }}</h2>
<ul class="y-tiles" style="--cols: 6">
    @foreach ($division['items'] as $i => [$name, $note, $icon])
        <li><x-site-tile :src="$sitePhoto($divisionKey.'-'.($i + 1))" :label="$name" :note="$note" :icon="$icon" /></li>
    @endforeach
</ul>

<div class="y-duo">
    <section class="y-box">
        <h2 class="y-heading">{{ $division['check']['heading'] }}</h2>
        <div class="y-check">
            @php $inspection = $sitePhoto('maintenance-inspection'); @endphp
            <span class="y-check-photo" @if ($inspection) style="background-image: url('{{ $inspection }}')" @endif>@unless ($inspection)<x-site-icon name="car" />@endunless</span>
            <div>
                <p class="y-check-lead">{{ $br($division['check']['lead']) }}</p>
                <ul class="y-checklist">
                    @foreach ($division['check']['points'] as $point)
                        <li><x-site-icon name="check" />{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
    <section class="y-box">
        <h2 class="y-heading y-heading-icon"><x-site-icon name="gear" />{{ $division['flow_heading'] }}</h2>
        <x-site-flow :steps="$division['flow']" />
    </section>
</div>
