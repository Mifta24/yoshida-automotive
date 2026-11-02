<h2 class="y-heading y-heading-bar"><x-site-icon name="tire" />{{ $division['items_heading'] }} <small>{{ $division['items_sub'] }}</small></h2>
<ul class="y-tiles y-tiles-service" style="--cols: 5">
    @foreach ($division['items'] as $i => [$name, $note, $icon])
        <li><x-site-tile :src="$sitePhoto($divisionKey.'-'.($i + 1))" :label="$name" :note="$note" :icon="$icon" /></li>
    @endforeach
</ul>

<h2 class="y-heading y-heading-bar"><x-site-icon name="tire" />{{ $division['types_heading'] }} <small>{{ $division['types_sub'] }}</small></h2>
<div class="y-types">
    @foreach ($division['types'] as [$tone, $icon, $name, $note])
        @php $typePhoto = $sitePhoto('tires-'.$tone); @endphp
        <article class="y-type y-type-{{ $tone }}" @if ($typePhoto) style="--y-photo: url('{{ $typePhoto }}')" @endif><x-site-icon :name="$icon" /><strong>{{ $name }}</strong><small>{{ $br($note) }}</small></article>
    @endforeach
    <aside class="y-brands">
        <p>{{ $division['brands_heading'] }}</p>
        <ul>@foreach ($division['brands'] as $brand)<li>{{ $brand }}</li>@endforeach</ul>
    </aside>
</div>

<p class="y-banner">
    <span class="y-banner-logo">X-Change<small>{{ $t('エクスチェンジ') }}</small></span>
    <span class="y-banner-note">{{ $br($division['external']['note']) }}</span>
    <a href="{{ $division['external']['url'] }}" target="_blank" rel="noopener">{{ $division['external']['label'] }} <span aria-hidden="true">↗</span><small>{{ $division['external']['url'] }}</small></a>
</p>

<div class="y-duo y-duo-shop">
    <section class="y-box">
        <h2 class="y-heading y-heading-icon"><x-site-icon name="warehouse" />{{ $t('X-CHANGE 店舗イメージ') }}</h2>
        @php $shop = $sitePhoto('tires-shop'); @endphp
        <span class="y-shop-photo" @if ($shop) style="background-image: url('{{ $shop }}')" @endif></span>
    </section>
    <section class="y-box">
        <h2 class="y-heading y-heading-icon"><x-site-icon name="tire" />{{ $division['flow_heading'] }} <small>{{ $division['flow_sub'] }}</small></h2>
        <x-site-flow :steps="$division['flow']" />
    </section>
</div>
