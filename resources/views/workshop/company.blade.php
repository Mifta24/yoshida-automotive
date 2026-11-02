<x-workshop-stage :site="$site" :prefill="$prefill" :workshop="$workshop" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :garage="$garage" :narration="$narration" :scene="$scene" :backdrop="$backdrop" :menu-items="$menuItems" :wizard="$wizard" :service-terms="$serviceTerms" :today="$today" :tour-stops="$tourStops" :menu="false" :title="'会社案内 · '.$workshop->name">
    <article class="y-company" lang="ja">
        <nav class="y-crumbs" aria-label="パンくず"><a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit>ホーム</a> › 会社案内 › 代表者挨拶</nav>
        <header class="y-company-title"><h1>会社案内</h1><span aria-hidden="true">/</span><p>代表者挨拶</p></header>
        <div class="y-company-grid">
            <div class="y-president" @if ($photo) style="--y-photo: url('{{ $photo }}')" @endif>
                <p class="y-president-role">{{ $company['role'] }}</p>
                <p class="y-president-name">{{ $company['name'] }}</p>
                <p class="y-president-en">{{ $company['name_en'] }}</p>
            </div>
            <div class="y-greeting">
                <h2>{{ $company['heading_1'] }}<br><em>{{ $company['heading_accent'] }}</em>{{ $company['heading_2'] }}</h2>
                @foreach ($company['paragraphs'] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
            <aside class="y-values">
                <h2 class="y-heading">私たちの想い</h2>
                <ul>
                    @foreach ($company['values'] as $index => $value)
                        <li><x-site-icon :name="['shield', 'tools', 'car'][$index]" /><span>{{ $value }}</span></li>
                    @endforeach
                </ul>
                <p class="y-sign"><span>{{ $company['company'] }}<br>{{ $company['role'] }}</span><strong>{{ $company['name'] }}</strong></p>
            </aside>
        </div>
    </article>
</x-workshop-stage>
