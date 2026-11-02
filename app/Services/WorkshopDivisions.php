<?php

namespace App\Services;

/**
 * The five business lines of the Yoshida Automotive site and the wording of its
 * home and company pages. The detail panels are written in Japanese only; the
 * home page and navigation exist in every supported language.
 */
class WorkshopDivisions
{
    /**
     * @return array<string, string>
     */
    public static function siteLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'nav_services' => 'Services', 'nav_cases' => 'Repair cases', 'nav_company' => 'Company', 'nav_access' => 'Access', 'nav_ai' => 'AI consult & booking',
                'hero_line_1' => 'Everything for your car,', 'hero_line_2' => 'with trust and skill.', 'hero_sub' => 'Body & paint, maintenance, insurance, sales and tires in one place',
                'hero_ai' => 'Ask the AI for free', 'hero_services' => 'See services', 'panel_title' => 'AI Concierge', 'panel_online' => 'Online',
                'panel_greeting' => 'Hello. I am the AI Concierge of Yoshida Automotive. How can I help you?', 'panel_placeholder' => 'Type your question…',
                'quick_repair' => 'I need a scratch repaired', 'quick_inspection' => 'I want to book an inspection', 'quick_tire' => 'I want to change my tires',
                'theme_light' => 'Switch to light theme', 'theme_dark' => 'Switch to dark theme', 'close' => 'Close',
            ],
            'id' => [
                'nav_services' => 'Layanan', 'nav_cases' => 'Contoh perbaikan', 'nav_company' => 'Perusahaan', 'nav_access' => 'Akses', 'nav_ai' => 'Konsultasi AI & booking',
                'hero_line_1' => 'Semua untuk mobil Anda,', 'hero_line_2' => 'dengan tenang dan terampil.', 'hero_sub' => 'Bodi & cat, servis, asuransi, penjualan, dan ban dalam satu tempat',
                'hero_ai' => 'Konsultasi AI gratis', 'hero_services' => 'Lihat layanan', 'panel_title' => 'AI Concierge', 'panel_online' => 'Online',
                'panel_greeting' => 'Halo. Saya AI Concierge Yoshida Automotive. Ada yang bisa saya bantu?', 'panel_placeholder' => 'Tulis pertanyaan Anda…',
                'quick_repair' => 'Saya ingin memperbaiki baret', 'quick_inspection' => 'Saya ingin booking inspeksi', 'quick_tire' => 'Saya ingin ganti ban',
                'theme_light' => 'Ganti ke tema terang', 'theme_dark' => 'Ganti ke tema gelap', 'close' => 'Tutup',
            ],
            default => [
                'nav_services' => 'サービス', 'nav_cases' => '修理事例', 'nav_company' => '会社案内', 'nav_access' => 'アクセス', 'nav_ai' => 'AI相談・予約',
                'hero_line_1' => '愛車のすべてを、', 'hero_line_2' => '安心と技術で。', 'hero_sub' => '板金・整備・保険・販売・タイヤの総合サービス',
                'hero_ai' => 'AIに無料相談', 'hero_services' => 'サービスを見る', 'panel_title' => 'AIコンシェルジュ', 'panel_online' => 'オンライン',
                'panel_greeting' => 'こんにちは。吉田自動車工業のAIコンシェルジュです。ご相談内容をお聞かせください。', 'panel_placeholder' => 'ご相談内容を入力してください…',
                'quick_repair' => 'キズ修理の相談をしたい', 'quick_inspection' => '車検の予約をしたい', 'quick_tire' => 'タイヤ交換をしたい',
                'theme_light' => 'ライトテーマに切り替え', 'theme_dark' => 'ダークテーマに切り替え', 'close' => '閉じる',
            ],
        };
    }

    /**
     * The home page cards: one per division, in every language.
     *
     * @return list<array{key: string, icon: string, title: string, text: string}>
     */
    public static function cards(string $locale): array
    {
        $copy = [
            'ja' => [
                'body-paint' => ['板金・塗装', "キズ・へこみ・事故修理・\nボディリペア"],
                'maintenance' => ['整備&車検', "点検・メンテナンスから\n車検までトータルに対応"],
                'insurance' => ['自動車保険', "事故対応・保険の見直し・\n最適なプランのご提案"],
                'sales' => ['オークション販売', "全国のオークション会場から\nご希望の一台をお探しします"],
                'tires' => ['タイヤ販売・交換', "タイヤの購入・交換・保管まで\nX-CHANGEでスマートに"],
            ],
            'en' => [
                'body-paint' => ['Body & paint', 'Scratches, dents, accident and body repair'],
                'maintenance' => ['Maintenance & inspection', 'From routine checks to the statutory inspection'],
                'insurance' => ['Auto insurance', 'Accident support, policy reviews and the right plan'],
                'sales' => ['Auction sales', 'We find your car at auctions across the country'],
                'tires' => ['Tire sales & change', 'Buy, change and store tires with X-CHANGE'],
            ],
            'id' => [
                'body-paint' => ['Bodi & cat', 'Baret, penyok, perbaikan kecelakaan dan bodi'],
                'maintenance' => ['Servis & inspeksi', 'Dari pengecekan rutin sampai inspeksi berkala'],
                'insurance' => ['Asuransi mobil', 'Bantuan kecelakaan, tinjauan polis, dan paket yang tepat'],
                'sales' => ['Penjualan lelang', 'Kami carikan mobil Anda dari lelang di seluruh negeri'],
                'tires' => ['Penjualan & ganti ban', 'Beli, ganti, dan simpan ban bersama X-CHANGE'],
            ],
        ];

        $icons = ['body-paint' => 'car', 'maintenance' => 'tools', 'insurance' => 'shield', 'sales' => 'gavel', 'tires' => 'tire'];
        $cards = [];

        foreach ($copy[$locale] ?? $copy['ja'] as $key => [$title, $text]) {
            $cards[] = ['key' => $key, 'icon' => $icons[$key], 'title' => $title, 'text' => $text];
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::DIVISIONS[$key] ?? null;
    }

    /**
     * Each entry is a list of [icon, label, note] steps.
     */
    private const FLOW_CONSULT = [['chat', 'ご相談', 'お問い合わせ'], ['search', 'お見積り', '状態の確認'], ['tools', '修理・塗装', '作業実施'], ['car', 'お引き渡し', 'ご説明']];

    /**
     * @var array<string, array<string, mixed>>
     */
    private const DIVISIONS = [
        'body-paint' => [
            'title' => '板金・塗装', 'english' => 'AUTO BODY & PAINT', 'icon' => 'car',
            'lead' => "キズ・へこみ・事故修理から、\n美しい仕上がりの塗装まで。",
            'body' => "小さなキズの修理から、事故による大きな損傷の復元、\n保険修理まで。熟練の技術と最新設備で\nお客様の愛車を美しく、安心の状態へと戻します。",
            'badges' => [['diamond', "高品質な\n仕上がり"], ['tools', "最新設備と\n確かな技術"], ['shield', "保険修理\n対応"], ['car', "代車\n無料（※条件あり）"]],
            'tabs' => ['サービス内容', '修理事例', '修理の流れ', '料金の目安', 'よくあるご質問'],
            'items_heading' => '対応できる主な修理・サービス',
            'items' => [['キズ・すりキズ修理', '', 'car', 'bp-scratch'], ['へこみ修理', '', 'car', 'bp-dent'], ['事故修理', '（保険対応可）', 'shield', 'bp-accident'], ['塗装・部分塗装', '', 'tools', 'bp-paint'], ['パーツ交換', '（バンパー・フェンダー等）', 'car', 'bp-parts']],
            'cases_heading' => '修理事例', 'cases_sub' => 'Before / After',
            'cases' => [['バンパーのキズ修理・塗装', '（作業目安：2〜3日）', 'bumper'], ['ドアのへこみ修理・塗装', '（作業目安：3〜5日）', 'door'], ['事故修理（保険対応）', '（作業目安：5〜10日）', 'collision']],
            'detail' => [['バンパーのキズ修理・塗装', '作業目安：2〜3日'], ['ドアのへこみ修理・塗装', '作業目安：3〜5日'], ['事故修理（保険対応）', '作業目安：5〜10日']],
            'flow_heading' => '修理の流れ', 'flow' => self::FLOW_CONSULT,
            'faq' => [['保険を使った修理はできますか？', 'はい、事故修理は保険対応が可能です。詳しくはスタッフにご相談ください。'], ['修理中の代車はありますか？', '代車は無料でご用意しています（※条件あり）。']],
            'services' => ['scratch-repair', 'dent-repair'], 'ask' => '板金・塗装について相談したいです。',
            'ai_title' => 'AIコンシェルジュに相談する', 'ai_text' => '写真を送って簡単見積り・修理のご相談ができます。',
            'book_title' => '見積り・修理を予約する', 'book_text' => 'ご希望の日時でご予約いただけます。',
        ],
        'maintenance' => [
            'title' => '整備&車検', 'english' => 'MAINTENANCE & INSPECTION', 'icon' => 'tools',
            'lead' => "日常点検から車検まで、\n愛車のコンディションを\nトータルにサポートします。",
            'body' => '',
            'badges' => [['engine', "定期点検\nメンテナンス"], ['clipboard', "車検\n（継続検査）"], ['oil', "オイル交換\n各種消耗品"], ['battery', "バッテリー\n交換"], ['tire', "タイヤ\n交換・調整"]],
            'tabs' => ['サービス内容', '整備メニュー', '車検の流れ', '料金の目安', 'よくあるご質問'],
            'items_heading' => '対応できる主な整備・メンテナンス',
            'items' => [['オイル交換', '', 'oil'], ['ブレーキ整備', '', 'wheel'], ['バッテリー交換', '', 'battery'], ["エアコン・\nフィルター交換", '', 'gear'], ["タイヤ交換・\nローテーション", '', 'tire'], ['診断・不具合修理', '（チェックランプ対応）', 'engine']],
            'check' => ['heading' => '車検（継続検査）', 'lead' => "国家基準に基づいた\n安心・確実な車検サービス", 'points' => ['法定24ヶ月点検', '各部の安全確認・整備', '必要な部品交換のご提案', '車検後のアフターサポート']],
            'detail' => [['法定24ヶ月点検', ''], ['各部の安全確認・整備', ''], ['必要な部品交換のご提案', ''], ['車検後のアフターサポート', '']],
            'flow_heading' => '整備の流れ', 'flow' => [['calendar', 'ご予約', '（お問い合わせ）'], ['search', '点検・診断', '（お見積り）'], ['tools', '整備・交換', '（作業実施）'], ['car', 'お引き渡し', '（ご説明）']],
            'faq' => [['車検はどのくらい前に予約できますか？', 'ご希望の日程をAIコンシェルジュまたはスタッフにお知らせください。'], ['点検だけでもお願いできますか？', 'はい、定期点検のみでも承ります。']],
            'services' => ['oil-change', 'vehicle-inspection'], 'ask' => '整備・車検について相談したいです。',
            'ai_title' => 'AIコンシェルジュに相談する', 'ai_text' => '整備内容・車検の時期・費用の目安をAIがご案内します。',
            'book_title' => '整備・車検の予約をする', 'book_text' => 'ご希望の日時でご予約いただけます。',
        ],
        'insurance' => [
            'title' => '自動車保険', 'english' => 'AUTO INSURANCE', 'icon' => 'shield',
            'lead' => "お客様のカーライフに合わせた\n最適な自動車保険プランをご提案。\n万一の事故にも、迅速・丁寧にサポートします。",
            'body' => '',
            'badges' => [['shield', "充実の\n補償内容"], ['coins', "最適な\n保険料"], ['headset', "事故時の\n安心サポート"], ['doc', "更新・見直し\nのご相談"], ['car', "代車\nサービス"]],
            'tabs' => ['サービス内容', '補償内容', '事故対応の流れ', '保険料の目安', 'よくあるご質問'],
            'items_heading' => '吉田自動車工業の自動車保険サービス',
            'items_sub' => 'お客様のご利用状況・ご予算に合わせて、国内主要保険会社の中から最適なプランをご提案します。',
            'items' => [["万一の事故も安心\n充実の補償", '', 'shield'], ["事故時の\nレッカー・代車対応", '', 'car'], ["修理・保険手続き\nまで一括サポート", '', 'doc'], ["ライフスタイルに合わせた\n最適な保険プラン", '', 'coins']],
            'coverage_heading' => '主な補償内容',
            'coverage' => [['car', '対人賠償', '（相手への補償）'], ['car', '対物賠償', '（物への補償）'], ['person', '人身傷害', '（ご自身・同乗者の補償）'], ['car', '車両保険', '（お車の補償）']],
            'detail' => [['対人賠償', '相手への補償'], ['対物賠償', '物への補償'], ['人身傷害', 'ご自身・同乗者の補償'], ['車両保険', 'お車の補償']],
            'flow_heading' => 'ご相談から契約までの流れ', 'flow' => [['chat', 'ご相談', '（ヒアリング）'], ['doc', 'プランご提案', '（お見積り）'], ['handshake', 'ご契約', '（お手続き）'], ['car', 'アフターサポート', '（事故時の対応）']],
            'faq' => [['保険の見直しだけでも相談できますか？', 'はい、更新や見直しのご相談も承ります。'], ['事故のときはどうすればいいですか？', 'まずはスタッフにご連絡ください。レッカー・代車の対応もご案内します。']],
            'services' => ['insurance-consultation'], 'ask' => '自動車保険について相談したいです。',
            'ai_title' => 'AIコンシェルジュに相談する', 'ai_text' => '保険プランのご相談・見積り・資料のご請求ができます。',
            'book_title' => '自動車保険の相談・見積りを予約する', 'book_text' => '専門スタッフが最適なプランをご提案します。',
        ],
        'sales' => [
            'title' => '車販売', 'english' => 'CAR SALES', 'icon' => 'car',
            'lead' => "新車・中古車の販売から、\nご希望の一台探し、購入後のアフターサポートまで\nトータルにサポートします。",
            'body' => '',
            'badges' => [['car', '新車販売'], ['car', '中古車販売'], ['search', "車探し\n（注文販売）"], ['doc', "ローン\n各種プラン"], ['shield', "購入後の\nサポート"]],
            'tabs' => ['サービス内容', '在庫車一覧', '購入の流れ', 'ローン・お支払い', 'よくあるご質問'],
            'items_heading' => '取り扱い車種', 'items_sub' => '軽自動車からSUV・商用車まで、幅広いラインアップをご用意しています。',
            'items' => [['軽自動車', '', 'car'], ["コンパクト・\nセダン", '', 'car'], ['SUV', '', 'car'], ['ミニバン', '', 'car'], ['商用車', '（バン・トラック）', 'car']],
            'stock_heading' => 'おすすめの在庫車', 'stock_sub' => '最新の在庫情報を随時更新中！',
            'stock' => [['トヨタ RAV4', '（中古車）', 'おすすめ'], ['トヨタ アルファード', '（中古車）', '人気'], ['トヨタ プリウス', '（中古車）', '新着']],
            'detail' => [['トヨタ RAV4', '中古車'], ['トヨタ アルファード', '中古車'], ['トヨタ プリウス', '中古車']],
            'flow_heading' => 'ご購入の流れ', 'flow' => [['search', '車を探す', '（ご相談）'], ['doc', 'ご提案', '（見積り）'], ['coins', 'ご契約', '（お手続き）'], ['car', '納車', '（アフターサポート）']],
            'faq' => [['希望の車を探してもらえますか？', 'はい、全国のオークション会場からご希望の一台をお探しします。'], ['ローンは利用できますか？', '各種プランをご用意しています。詳しくはスタッフにご相談ください。']],
            'services' => ['car-sales-consultation'], 'ask' => '車の購入について相談したいです。',
            'ai_title' => 'AIコンシェルジュに相談する', 'ai_text' => 'ご希望の車種・条件・ご予算をAIがご提案します。',
            'book_title' => '車の相談・見積りを予約する', 'book_text' => 'ご希望の日時でご相談いただけます。',
        ],
        'tires' => [
            'title' => 'タイヤ販売・交換', 'english' => 'TIRE SALES & EXCHANGE', 'icon' => 'tire',
            'lead' => '',
            'body' => "タイヤのご購入から交換・取付、季節ごとのタイヤ履き替え、\nシーズンオフの保管サービスまで、幅広く対応。\n経験豊富なプロのスタッフが、おクルマの使用状況やご予算に合わせて\n最適なタイヤ選びとメンテナンスをサポートいたします。",
            'badges' => [['tire', 'タイヤ購入'], ['tools', '交換作業'], ['warehouse', '保管サービス'], ['wheel', 'ホイール相談']],
            'tabs' => ['サービス内容', '取扱タイヤ', '交換の流れ', '料金の目安', 'よくあるご質問'],
            'items_heading' => '対応サービス', 'items_sub' => 'タイヤに関する幅広いサービスで、安心・快適なカーライフをサポートします。',
            'items' => [['タイヤ交換', '新品タイヤの取付・交換', 'tire'], ['季節の履き替え', '夏タイヤ⇔冬タイヤのシーズン交換', 'tire'], ['パンク修理', 'パンクの点検・修理（応急・本修理対応）', 'tools'], ['ホイールアライメント', 'タイヤの偏摩耗を防ぎ走行安定性を向上', 'wheel'], ['タイヤ保管（預かりサービス）', 'シーズンオフのタイヤを専用環境で安心保管', 'warehouse']],
            'types_heading' => 'おすすめタイヤ / 取扱ブランド', 'types_sub' => '人気の国内外ブランドをはじめ、用途・ご予算に合わせたタイヤをご提案します。',
            'types' => [['premium', 'crown', 'プレミアムタイヤ', "静粛性・快適性・\n走行性能に優れた高性能モデル"], ['eco', 'leaf', 'エコタイヤ', "低燃費で家計にも\n環境にもやさしい省エネモデル"], ['studless', 'snow', 'スタッドレスタイヤ', "雪道でも安心のグリップ力\n冬の定番タイヤ"]],
            'brands_heading' => '主な取扱ブランド', 'brands' => ['AUTOWAY', 'NANKANG', 'ATR', 'HIFLY', 'ZEETEX', 'RADAR', 'MINERVA'],
            'detail' => [['プレミアムタイヤ', '静粛性・快適性・走行性能に優れた高性能モデル'], ['エコタイヤ', '低燃費で家計にも環境にもやさしい省エネモデル'], ['スタッドレスタイヤ', '雪道でも安心のグリップ力、冬の定番タイヤ']],
            'flow_heading' => 'ご利用の流れ', 'flow_sub' => '（タイヤ交換・購入の基本的な流れ）',
            'flow' => [['calendar', '予約', 'Web・お電話で簡単予約'], ['car', '来店', 'ご予約の日時に店舗へご来店'], ['search', '点検', 'タイヤの状態をプロがしっかり確認'], ['tire', '交換', 'タイヤ交換・取付作業を実施'], ['car', 'お渡し', '作業完了後、お車をお渡し']],
            'faq' => [['タイヤを預かってもらえますか？', 'はい、シーズンオフのタイヤを専用環境で保管します。'], ['直接問い合わせたいです。', 'タイヤサービス直通番号 04-2937-5296 までお電話ください。']],
            'services' => ['tire-change'], 'ask' => 'タイヤの購入・交換について相談したいです。',
            'external' => ['label' => 'X-CHANGE 公式サイトを見る', 'url' => 'https://x-change.tokyo', 'note' => "吉田自動車工業が提携する\nタイヤ専門ショップ「X-CHANGE」の公式サイトへ", 'phone' => '04-2937-5296'],
            'ai_title' => 'AIコンシェルジュに相談する', 'ai_text' => 'タイヤサイズの確認・最適なタイヤ選び・交換時期など、AIが丁寧にご案内します。',
            'book_title' => 'タイヤ交換・購入を予約する', 'book_text' => 'ご希望の日時で、タイヤの交換・ご購入の予約ができます。',
        ],
    ];

    /**
     * The president's greeting on the company page.
     *
     * @return array{name: string, name_en: string, role: string, company: string, heading_1: string, heading_accent: string, heading_2: string, paragraphs: list<string>, values: list<string>, phone: string, hours: string}
     */
    public static function company(): array
    {
        return [
            'name' => '吉田 直樹', 'name_en' => 'Naoki Yoshida', 'role' => '代表取締役社長', 'company' => '吉田自動車工業株式会社',
            'heading_1' => 'クルマの価値を守り、', 'heading_accent' => '長く愛される一台', 'heading_2' => 'をこれからも。',
            'paragraphs' => [
                '吉田自動車工業は、創業以来、地域の皆さまのクルマの「安心・安全」と「美しさ」を支えるパートナーとして、板金・塗装をはじめとする自動車整備に真摯に取り組んでまいりました。',
                'クルマは、単なる移動手段ではなく、お客様の仕事や暮らし、そして大切な思い出をともにする、かけがえのない存在です。私たちは、事故やキズで損なわれたクルマの価値を確かな技術でよみがえらせ、これからも安心して乗り続けていただけるよう、見た目の美しさと安全性の両立を追求しています。',
                '昨今、クルマを取り巻く環境は、技術の進化や多様なニーズにより大きく変化しています。そうした中でも、私たちは「人の技術」と「最新の設備」を融合させ、お客様一人ひとりに寄り添った、最適な修理・板金塗装サービスを提供し続けます。',
                'これからも、車業界・板金塗装のプロフェッショナルとして、地域に根ざし、クルマを通じて皆さまの安心で豊かなカーライフに貢献してまいります。',
            ],
            'values' => ['安心・安全の追求', '確かな板金技術', '長く乗れるクルマの価値創造'],
            'phone' => '042-556-1219', 'hours' => '9:00-18:00／日祝休',
        ];
    }
}
