<h2 class="y-heading">{{ $division['items_heading'] }} <small>{{ $division['items_sub'] }}</small></h2>
<ul class="y-tiles" style="--cols: 5">
    @foreach ($division['items'] as $i => [$name, $note, $icon])
        <li><x-site-tile :src="$sitePhoto($divisionKey.'-'.($i + 1))" :label="$name" :note="$note" :icon="$icon" /></li>
    @endforeach
</ul>

<div class="y-duo y-duo-stock">
    <section class="y-box">
        <h2 class="y-heading">{{ $division['stock_heading'] }} <small>{{ $division['stock_sub'] }}</small></h2>
        <p class="y-photo-note">掲載車両の写真はイメージです。実際の在庫車両とは異なります。</p>
        <ul class="y-stock">
            @foreach ($division['stock'] as $i => [$name, $note, $badge])
                @php $photo = $sitePhoto('sales-stock-'.($i + 1)); @endphp
                <li class="y-stockcard">
                    <span class="y-tile-photo" @if ($photo) style="background-image: url('{{ $photo }}')" @endif><b class="y-badge">{{ $badge }}</b>@unless ($photo)<x-site-icon name="car" />@endunless</span>
                    <strong>{{ $name }}</strong><small>{{ $note }}</small>
                </li>
            @endforeach
        </ul>
        <p class="y-dots" aria-hidden="true"><i class="is-on"></i><i></i><i></i></p>
    </section>
    <section class="y-box">
        <h2 class="y-heading y-heading-icon"><x-site-icon name="gear" />{{ $division['flow_heading'] }}</h2>
        <x-site-flow :steps="$division['flow']" />
    </section>
</div>
