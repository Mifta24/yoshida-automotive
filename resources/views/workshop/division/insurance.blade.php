<h2 class="y-heading">{{ $division['items_heading'] }}</h2>
<p class="y-sub">{{ $division['items_sub'] }}</p>
<ul class="y-tiles" style="--cols: 4">
    @foreach ($division['items'] as $i => [$name, $note, $icon])
        <li><x-site-tile :src="$sitePhoto($divisionKey.'-'.($i + 1))" :label="$name" :note="$note" :icon="$icon" /></li>
    @endforeach
</ul>

<div class="y-duo y-duo-even">
    <section class="y-box">
        <h2 class="y-heading">{{ $division['coverage_heading'] }}</h2>
        <ul class="y-coverage">
            @foreach ($division['coverage'] as [$icon, $name, $note])
                <li><x-site-icon :name="$icon" /><strong>{{ $name }}</strong><small>{{ $note }}</small></li>
            @endforeach
        </ul>
    </section>
    <section class="y-box">
        <h2 class="y-heading y-heading-icon"><x-site-icon name="gear" />{{ $division['flow_heading'] }}</h2>
        <x-site-flow :steps="$division['flow']" />
    </section>
</div>
