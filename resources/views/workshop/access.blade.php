@php
    $br = fn (string $text) => new \Illuminate\Support\HtmlString(nl2br(e($text)));
    $home = route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
    $reserve = route('workshop.reservation', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
    $address = collect([$workshop->address, $workshop->city])->filter()->implode(' ');
    $hasPoint = $workshop->latitude !== null && $workshop->longitude !== null;
    $target = $hasPoint ? (float) $workshop->latitude.','.(float) $workshop->longitude : $address;
    $mapUrl = $target !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($target) : null;
    $routeUrl = $target !== '' ? 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($target) : null;
    $opens = substr((string) $workshop->opening_time, 0, 5);
    $closes = substr((string) $workshop->closing_time, 0, 5);
    $hours = $opens !== '' && $closes !== '' ? ltrim($opens, '0').($locale === 'ja' ? '〜' : '–').$closes : null;
@endphp
<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="$t('アクセス').' · '.$workshop->name">
    <article class="y-panel y-panel-access" lang="{{ $locale }}">
        <a href="{{ $home }}" data-stage-exit class="y-panel-close" aria-label="{{ $site['close'] }}"><span aria-hidden="true">×</span></a>

        <div class="y-panel-side" @if ($photo) style="--y-photo: url('{{ $photo }}')" @endif>
            <div class="y-side-logo"><x-site-logo /><span><strong>YOSHIDA AUTOMOTIVE</strong><small>吉田自動車工業</small></span></div>
            <div class="y-side-main">
                <x-site-icon name="pin" class="y-side-icon" />
                <h1>{{ $t('アクセス') }}</h1>
                <p class="y-side-en">ACCESS</p>
                <p class="y-side-lead">{{ $br($t("ご来店・お問い合わせは\nお気軽にどうぞ。")) }}</p>
            </div>
            <ul class="y-badges">
                @if ($workshop->phone)<li><x-site-icon name="phone" /><span>{{ $workshop->phone }}</span></li>@endif
                @if ($hours)<li><x-site-icon name="clock" /><span>{{ $t('営業時間') }}<br>{{ $hours }}</span></li>@endif
                <li><x-site-icon name="calendar" /><span>{{ $t('定休日') }}<br>{{ $t('日曜・祝日') }}</span></li>
            </ul>
        </div>

        <div class="y-panel-main">
            <h2 class="y-heading">{{ $t('店舗情報') }}</h2>
            <dl class="y-info">
                <div><dt>{{ $t('会社名') }}</dt><dd>{{ $locale === 'ja' ? ($workshop->translations['ja']['name'] ?? $workshop->name) : $workshop->name }}</dd></div>
                <div><dt>{{ $t('住所') }}</dt><dd>@if ($address !== ''){{ $address }}@else<span class="y-muted">{{ $t('準備中') }}</span>@endif</dd></div>
                @if ($workshop->phone)<div><dt>{{ $t('電話') }}</dt><dd><a href="tel:{{ $workshop->phone }}">{{ $workshop->phone }}</a></dd></div>@endif
                @if ($hours)<div><dt>{{ $t('営業時間') }}</dt><dd>{{ $hours }} {{ $t('（日曜・祝日は定休日）') }}</dd></div>@endif
                <div><dt>{{ $t('タイヤサービス') }}</dt><dd><a href="tel:{{ $tiresPhone }}">{{ $tiresPhone }}</a> {{ $t('（直通）') }}</dd></div>
            </dl>

            <h2 class="y-heading">{{ $t('地図') }}</h2>
            <div class="y-map">
                <x-site-icon name="pin" />
                @if ($mapUrl)
                    <p>{{ $address !== '' ? $address : $t('店舗の位置') }}</p>
                    <div class="y-map-actions">
                        <a href="{{ $mapUrl }}" target="_blank" rel="noopener">{{ $t('Googleマップで開く') }} <span aria-hidden="true">↗</span></a>
                        <a href="{{ $routeUrl }}" target="_blank" rel="noopener">{{ $t('経路を検索') }} <span aria-hidden="true">↗</span></a>
                    </div>
                @else
                    <p>{{ $t('地図は住所の登録後に表示されます。') }}</p>
                @endif
            </div>
        </div>

        <button type="button" class="y-cta y-cta-ai" data-hero-quick-message="{{ $t('お店の場所と営業時間を教えてください。') }}">
            <x-site-icon name="robot" /><span><strong>{{ $t('AIコンシェルジュに相談する') }}</strong><small>{{ $t('場所・営業時間・お問い合わせ方法をAIがご案内します。') }}</small></span><i aria-hidden="true">➤</i>
        </button>
        <a class="y-cta y-cta-book" href="{{ $reserve }}" data-stage-exit>
            <x-site-icon name="calendar" /><span><strong>{{ $t('ご来店・ご相談を予約する') }}</strong><small>{{ $t('ご希望の日時でご予約いただけます。') }}</small></span><i aria-hidden="true">»</i>
        </a>
    </article>
</x-workshop-stage>
