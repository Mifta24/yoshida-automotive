<h2 class="y-heading">{{ $division['items_heading'] }}</h2>
<p class="y-photo-note">掲載画像は施工イメージです。</p>
<ul class="y-tiles" style="--cols: 5">
    @foreach ($division['items'] as $i => $item)
        <li><x-site-tile :src="$sitePhoto($item[3] ?? $divisionKey.'-'.($i + 1))" :label="$item[0]" :note="$item[1]" :icon="$item[2]" /></li>
    @endforeach
</ul>

<h2 class="y-heading">{{ $division['cases_heading'] }} <small>{{ $division['cases_sub'] }}</small></h2>
<div class="y-cases">
    @foreach ($division['cases'] as $i => [$name, $note, $key])
        @php
            $before = $sitePhoto('bp-'.$key.'-before');
            $after = $sitePhoto('bp-'.$key.'-after');
        @endphp
        <figure class="y-case">
            <div class="y-case-photos">
                <span class="y-case-half" @if ($before) style="background-image: url('{{ $before }}')" @endif><em>Before</em></span>
                <span class="y-case-half" @if ($after) style="background-image: url('{{ $after }}')" @endif><em>After</em></span>
                <i aria-hidden="true">›</i>
            </div>
            <figcaption><strong>{{ $name }}</strong><small>{{ $note }}</small></figcaption>
        </figure>
    @endforeach
</div>
<p class="y-dots" aria-hidden="true"><i class="is-on"></i><i></i><i></i><i></i></p>
