@php
    $home = route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
    $reserve = route('workshop.reservation', ['workshopSlug' => $workshop->slug, 'lang' => $locale, 'service' => $division['services'][0] ?? null]);
@endphp
<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$division['title'].' · '.$workshop->name">
    <article class="y-panel" lang="ja">
        <a href="{{ $home }}" data-stage-exit class="y-panel-close" aria-label="{{ $site['close'] }}"><span aria-hidden="true">×</span></a>

        <div class="y-panel-side" @if ($photo) style="--y-photo: url('{{ $photo }}')" @endif>
            <x-site-icon :name="$division['icon']" class="y-side-icon" />
            <h1>{{ $division['title'] }}</h1>
            <p class="y-side-en">{{ $division['english'] }}</p>
            <p class="y-side-lead">{{ $division['lead'] }}</p>
            <p class="y-side-body">{{ $division['body'] }}</p>
            <ul class="y-badges">
                @foreach ($division['badges'] as $badge)
                    <li>{{ $badge }}</li>
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
                <h2 class="y-heading">{{ $division['items_heading'] }}</h2>
                <ul class="y-tiles">
                    @foreach ($division['items'] as [$name, $note])
                        <li><x-site-icon :name="$division['icon']" /><strong>{{ $name }}</strong>@if ($note)<span>{{ $note }}</span>@endif</li>
                    @endforeach
                </ul>
                <div class="y-duo">
                    <div>
                        <h2 class="y-heading">{{ $division['cases_heading'] }}</h2>
                        <ul class="y-list">
                            @foreach ($division['cases'] as [$name, $note])
                                <li><strong>{{ $name }}</strong>@if ($note)<span>{{ $note }}</span>@endif</li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <h2 class="y-heading">{{ $division['flow_heading'] }}</h2>
                        <ol class="y-flow">
                            @foreach ($division['flow'] as [$name, $note])
                                <li><strong>{{ $name }}</strong><span>{{ $note }}</span></li>
                            @endforeach
                        </ol>
                    </div>
                </div>
                @isset ($division['external'])
                    <p class="y-external">
                        <span>{{ $division['external']['note'] }}</span>
                        <a href="{{ $division['external']['url'] }}" target="_blank" rel="noopener">{{ $division['external']['label'] }} <span aria-hidden="true">↗</span></a>
                        <a href="tel:{{ $division['external']['phone'] }}">{{ $division['external']['phone'] }}</a>
                    </p>
                @endisset
            </section>

            <section role="tabpanel" id="y-pane-1" aria-labelledby="y-tab-1" @if ($openTab !== 1) hidden @endif>
                <h2 class="y-heading">{{ $division['cases_heading'] }}</h2>
                <ul class="y-list y-list-wide">
                    @foreach ($division['cases'] as [$name, $note])
                        <li><strong>{{ $name }}</strong>@if ($note)<span>{{ $note }}</span>@endif</li>
                    @endforeach
                </ul>
            </section>

            <section role="tabpanel" id="y-pane-2" aria-labelledby="y-tab-2" @if ($openTab !== 2) hidden @endif>
                <h2 class="y-heading">{{ $division['flow_heading'] }}</h2>
                <ol class="y-flow">
                    @foreach ($division['flow'] as [$name, $note])
                        <li><strong>{{ $name }}</strong><span>{{ $note }}</span></li>
                    @endforeach
                </ol>
            </section>

            <section role="tabpanel" id="y-pane-3" aria-labelledby="y-tab-3" @if ($openTab !== 3) hidden @endif>
                <h2 class="y-heading">{{ $division['tabs'][3] }}</h2>
                <ul class="y-list y-list-wide">
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

        <div class="y-panel-cta">
            <button type="button" class="y-cta y-cta-ai" data-hero-quick-message="{{ $division['ask'] }}">
                <x-site-icon name="chat" /><span><strong>{{ $division['ai_title'] }}</strong><small>{{ $division['ai_text'] }}</small></span><i aria-hidden="true">➤</i>
            </button>
            <a class="y-cta y-cta-book" href="{{ $reserve }}" data-stage-exit>
                <x-site-icon name="calendar" /><span><strong>{{ $division['book_title'] }}</strong><small>{{ $division['book_text'] }}</small></span><i aria-hidden="true">»</i>
            </a>
        </div>
    </article>
</x-workshop-stage>
