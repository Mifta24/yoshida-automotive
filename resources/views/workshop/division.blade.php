@php
    $br = fn (string $text) => new \Illuminate\Support\HtmlString(nl2br(e($text)));
    $home = route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
    $reserve = route('workshop.reservation', ['workshopSlug' => $workshop->slug, 'lang' => $locale, 'service' => $division['services'][0] ?? null]);
    $isTires = $divisionKey === 'tires';
@endphp
<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$division['title'].' · '.$workshop->name">
    <article class="y-panel y-panel-{{ $divisionKey }}" lang="{{ $locale }}">
        @if ($isTires)
            <header class="y-tires-bar">
                <x-site-logo class="y-bar-logo" />
                <p><strong>{{ $workshop->translations['ja']['name'] ?? $workshop->name }}</strong><small>YOSHIDA AUTOMOTIVE</small></p>
                <span class="y-bar-sep" aria-hidden="true">/</span>
                <span class="y-bar-title">{{ $t('タイヤサービス') }}</span>
                <em>– X-Change –</em>
                <a class="y-bar-phone" href="tel:{{ $division['external']['phone'] }}"><x-site-icon name="phone" /> {{ $t('直通番号') }} <strong>{{ $division['external']['phone'] }}</strong></a>
                <a href="{{ $home }}" data-stage-exit class="y-panel-close" aria-label="{{ $site['close'] }}"><span aria-hidden="true">×</span></a>
            </header>
        @else
            <a href="{{ $home }}" data-stage-exit class="y-panel-close" aria-label="{{ $site['close'] }}"><span aria-hidden="true">×</span></a>
        @endif

        <div class="y-panel-side" @if ($photo) style="--y-photo: url('{{ $photo }}')" @endif>
            @if ($isTires)
                <div class="y-xchange"><span class="y-xlogo">TIRE SHOP<b>X-Change</b></span><em>X-Change</em><small>{{ $t('エクスチェンジ') }}</small></div>
                @php $shopSide = $sitePhoto('tires-shop'); @endphp
                <span class="y-shop-photo y-shop-side" @if ($shopSide) style="background-image: url('{{ $shopSide }}')" @endif></span>
            @else
                <div class="y-side-logo"><x-site-logo /><span><strong>YOSHIDA AUTOMOTIVE</strong><small>吉田自動車工業</small></span></div>
            @endif
            <div class="y-side-main">
                @unless ($isTires)<x-site-icon :name="$division['icon']" class="y-side-icon" />@endunless
                <h1>{{ $division['title'] }}</h1>
                <p class="y-side-en">{{ $division['english'] }}</p>
                @if ($division['lead'] !== '')<p class="y-side-lead">{{ $br($division['lead']) }}</p>@endif
                @if ($division['body'] !== '')<p class="y-side-body">{{ $br($division['body']) }}</p>@endif
            </div>
            <ul class="y-badges">
                @foreach ($division['badges'] as [$icon, $label])
                    <li><x-site-icon :name="$icon" /><span>{{ $br($label) }}</span></li>
                @endforeach
            </ul>
        </div>

        <div class="y-panel-main" data-site-tabs>
            <div class="y-tabs" role="tablist">
                @foreach ($division['tabs'] as $index => $tab)
                    <button type="button" role="tab" id="y-tab-{{ $index }}" aria-controls="y-pane-{{ $index }}" aria-selected="{{ $index === $openTab ? 'true' : 'false' }}">{{ $tab }}</button>
                @endforeach
            </div>

            <section role="tabpanel" id="y-pane-0" aria-labelledby="y-tab-0" @if ($openTab !== 0) hidden @endif>
                @include('workshop.division.'.$divisionKey)
            </section>

            <section role="tabpanel" id="y-pane-1" aria-labelledby="y-tab-1" @if ($openTab !== 1) hidden @endif>
                <h2 class="y-heading">{{ $division['tabs'][1] }}</h2>
                <ul class="y-list">
                    @foreach ($division['detail'] as [$name, $note])
                        <li><strong>{{ $name }}</strong>@if ($note)<span>{{ $note }}</span>@endif</li>
                    @endforeach
                </ul>
            </section>

            <section role="tabpanel" id="y-pane-2" aria-labelledby="y-tab-2" @if ($openTab !== 2) hidden @endif>
                <h2 class="y-heading">{{ $division['flow_heading'] }}</h2>
                <x-site-flow :steps="$division['flow']" />
            </section>

            <section role="tabpanel" id="y-pane-3" aria-labelledby="y-tab-3" @if ($openTab !== 3) hidden @endif>
                <h2 class="y-heading">{{ $division['tabs'][3] }}</h2>
                <ul class="y-list">
                    @forelse ($divisionServices as $service)
                        <li><strong>{{ $service->translatedName($locale) }}</strong><span>{{ $workshop->money($service->base_price) }}{{ $labels['price_suffix'] }}</span></li>
                    @empty
                        <li><strong>{{ $garage['services_empty'] }}</strong></li>
                    @endforelse
                </ul>
                <p class="y-note">{{ $garage['availability_note'] }}</p>
            </section>

            <section role="tabpanel" id="y-pane-4" aria-labelledby="y-tab-4" @if ($openTab !== 4) hidden @endif>
                <h2 class="y-heading">{{ $division['tabs'][4] }}</h2>
                <dl class="y-faq">
                    @foreach ($division['faq'] as [$question, $answer])
                        <div><dt>{{ $question }}</dt><dd>{{ $answer }}</dd></div>
                    @endforeach
                </dl>
            </section>
        </div>

        <button type="button" class="y-cta y-cta-ai" data-hero-quick-message="{{ $division['ask'] }}">
            <x-site-icon name="robot" /><span><strong>{{ $division['ai_title'] }}</strong><small>{{ $division['ai_text'] }}</small></span><i aria-hidden="true">➤</i>
        </button>
        <a class="y-cta y-cta-book" href="{{ $reserve }}" data-stage-exit>
            <x-site-icon name="calendar" /><span><strong>{{ $division['book_title'] }}</strong><small>{{ $division['book_text'] }}</small></span><i aria-hidden="true">»</i>
        </a>
    </article>
</x-workshop-stage>
