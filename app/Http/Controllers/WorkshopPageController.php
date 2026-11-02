<?php

namespace App\Http\Controllers;

use App\Core\Member\Support\MemberLabels;
use App\Models\ServiceType;
use App\Models\Workshop;
use App\Models\WorkshopKnowledgeItem;
use App\Services\Reservation\MemberBookings;
use App\Services\Reservation\ReservationHandover;
use App\Services\WorkshopDivisions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WorkshopPageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** The default cut-out of the advisor, layered in front of a scene's background when a scene does not name its own. */
    private const CHARACTER_IMAGE = 'images/character/character greeting.webp';

    public function __construct(
        private readonly ReservationHandover $handover,
        private readonly MemberBookings $memberBookings,
    ) {}

    /**
     * What the AI advisor says when the customer steps into the services scenes.
     * Every sentence is built from stored workshop data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'services' => 'Here are our services. We have :count service types, from :price. Open any of them and I will walk you through it, or ask me anything.',
            'services_one' => 'Here is our service. We have one service type, from :price. Open it and I will walk you through it, or ask me anything.',
            'duration' => 'It takes about :minutes minutes.',
            'warranty' => 'Workmanship warranty is included.',
            'price' => 'Prices start from :price. Final availability and prices are confirmed by our team.',
            'facilities' => 'We have :count facilities and services, including :examples. Choose one to read the details, or ask me anything.',
            'facilities_one' => 'We offer :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :workshop.',
            'info_place' => 'We are located in :location.',
            'info_hours' => 'We are open from :in to :out.',
            'info_more' => 'Below you will find the address, our policies and frequently asked questions — or just ask me.',
            'tour_services' => 'This way — let me show you our services.',
            'tour_facilities' => 'Come, let me show you the workshop facilities.',
            'tour_info' => 'Let me tell you more about the workshop.',
            'tour_staff' => 'Let me bring you to our team.',
            'tour_reservation' => 'Let me help you plan your visit.',
            'tour_garage' => 'Let me walk you back to the garage.',
            'tour_service' => 'Let me show you :name.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'services' => 'Inilah layanan-layanan kami. Ada :count tipe layanan, mulai dari :price. Buka salah satu, nanti saya jelaskan — atau tanyakan apa saja kepada saya.',
            'services_one' => 'Inilah layanan kami. Ada satu tipe layanan, mulai dari :price. Buka layanannya, nanti saya jelaskan — atau tanyakan apa saja kepada saya.',
            'duration' => 'Pengerjaannya sekitar :minutes menit.',
            'warranty' => 'Sudah termasuk garansi pengerjaan.',
            'price' => 'Harga mulai dari :price. Ketersediaan dan harga final dikonfirmasi oleh tim kami.',
            'facilities' => 'Kami memiliki :count fasilitas dan layanan, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'facilities_one' => 'Kami memiliki :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :workshop.',
            'info_place' => 'Kami berada di :location.',
            'info_hours' => 'Kami buka pukul :in sampai :out.',
            'info_more' => 'Di bawah ini ada alamat, kebijakan, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'tour_services' => 'Mari, saya antar ke layanan-layanan kami.',
            'tour_facilities' => 'Mari, saya antar ke fasilitas bengkel kami.',
            'tour_info' => 'Mari, saya ceritakan lebih lanjut tentang bengkel kami.',
            'tour_staff' => 'Mari, saya antar Anda bertemu tim kami.',
            'tour_reservation' => 'Mari, saya bantu rencanakan servis Anda.',
            'tour_garage' => 'Mari saya antar kembali ke garasi.',
            'tour_service' => 'Mari saya tunjukkan :name.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'services' => 'こちらがサービスです。:count 種類、:price からご用意しています。サービスを開いていただければ、ご案内いたします。ご質問もお気軽にどうぞ。',
            'services_one' => 'こちらがサービスです。1種類、:price からご用意しています。サービスを開いていただければ、ご案内いたします。ご質問もお気軽にどうぞ。',
            'duration' => '作業時間は約:minutes分です。',
            'warranty' => '作業保証が付いています。',
            'price' => '料金は :price からです。空き状況と料金は、スタッフが最終確認いたします。',
            'facilities' => ':count件の施設・サービスをご用意しています。:examples などです。選ぶと詳細をご覧いただけます。ご質問もどうぞ。',
            'facilities_one' => ':examples をご用意しています。詳細をご覧ください。ご質問もどうぞ。',
            'info_welcome' => 'ようこそ、:workshop へ。',
            'info_place' => '所在地は:locationです。',
            'info_hours' => '営業時間は:inから:outまでです。',
            'info_more' => '以下に、所在地、ご利用規定、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'tour_services' => 'こちらへどうぞ。サービスへご案内します。',
            'tour_facilities' => 'こちらへどうぞ。整備工場の施設へご案内します。',
            'tour_info' => '整備工場についてご案内します。',
            'tour_staff' => 'スタッフのもとへご案内します。',
            'tour_reservation' => 'ご入庫のご計画をお手伝いします。',
            'tour_garage' => '工場へご案内します。',
            'tour_service' => ':name をご案内します。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Customer-facing names for the coded service values stored in the database.
     *
     * @var array<string, array{vehicle_class: array<string, string>, inclusion: array<string, string>}>
     */
    private const SERVICE_TERMS = [
        'en' => [
            'vehicle_class' => ['all' => 'All vehicles', 'city_car' => 'City car', 'mpv' => 'MPV', 'suv' => 'SUV', 'truck' => 'Truck'],
            'inclusion' => ['engine_oil' => 'Engine oil', 'oil_filter' => 'Oil filter', 'air_filter' => 'Air filter', 'spark_plugs' => 'Spark plugs', 'brake_check' => 'Brake check', 'brake_pads' => 'Brake pads', 'computer_scan' => 'Computer scan', 'road_test' => 'Road test', 'wheel_balancing' => 'Wheel balancing', 'wheel_alignment' => 'Wheel alignment', 'ac_cleaning' => 'AC cleaning', 'freon_refill' => 'Refrigerant refill', 'car_wash' => 'Car wash'],
        ],
        'id' => [
            'vehicle_class' => ['all' => 'Semua kendaraan', 'city_car' => 'City car', 'mpv' => 'MPV', 'suv' => 'SUV', 'truck' => 'Truk'],
            'inclusion' => ['engine_oil' => 'Oli mesin', 'oil_filter' => 'Filter oli', 'air_filter' => 'Filter udara', 'spark_plugs' => 'Busi', 'brake_check' => 'Pengecekan rem', 'brake_pads' => 'Kampas rem', 'computer_scan' => 'Scan komputer', 'road_test' => 'Uji jalan', 'wheel_balancing' => 'Balancing roda', 'wheel_alignment' => 'Spooring', 'ac_cleaning' => 'Pembersihan AC', 'freon_refill' => 'Isi freon', 'car_wash' => 'Cuci mobil'],
        ],
        'ja' => [
            'vehicle_class' => ['all' => '全車種', 'city_car' => 'コンパクトカー', 'mpv' => 'ミニバン', 'suv' => 'SUV', 'truck' => 'トラック'],
            'inclusion' => ['engine_oil' => 'エンジンオイル', 'oil_filter' => 'オイルフィルター', 'air_filter' => 'エアフィルター', 'spark_plugs' => 'スパークプラグ', 'brake_check' => 'ブレーキ点検', 'brake_pads' => 'ブレーキパッド', 'computer_scan' => 'コンピューター診断', 'road_test' => '試運転', 'wheel_balancing' => 'ホイールバランス', 'wheel_alignment' => 'ホイールアライメント', 'ac_cleaning' => 'エアコン洗浄', 'freon_refill' => '冷媒補充', 'car_wash' => '洗車'],
        ],
    ];

    public function index(Request $request): View|RedirectResponse
    {
        $workshops = Workshop::where('public_status', 'published')->orderBy('name')->get();

        if ($workshops->count() === 1) {
            return redirect()->route('workshop.show', ['workshopSlug' => $workshops->first()->slug, 'lang' => $request->query('lang')]);
        }

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($workshops->first()?->default_locale ?? 'id');

        return view('opening', [
            'workshops' => $workshops,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
        ]);
    }

    public function show(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);
        $data = $this->withSiteBackdrop($this->stageData($workshop, $locale, 'garage'));

        return view('workshop.show', [
            ...$data,
            'divisionCards' => WorkshopDivisions::cards($locale),
            'divisionImages' => collect(WorkshopDivisions::cards($locale))->mapWithKeys(fn (array $card) => [$card['key'] => $this->sitePhoto($card['key'])])->all(),
        ]);
    }

    public function division(Request $request, string $workshopSlug, string $division): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        $details = WorkshopDivisions::find($division);
        abort_if($details === null, 404);

        $data = $this->withSiteBackdrop($this->stageData($workshop, $locale, 'services'));

        return view('workshop.division', [
            ...$data,
            'division' => $details,
            'divisionKey' => $division,
            'divisionServices' => $data['serviceTypes']->whereIn('slug', $details['services'])->values(),
            'photo' => $this->sitePhoto($division),
            'sitePhoto' => fn (string $name): ?string => $this->sitePhoto($name),
            'openTab' => min(max((int) $request->query('tab', 0), 0), 4),
        ]);
    }

    public function company(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.company', [
            ...$this->withSiteBackdrop($this->stageData($workshop, $locale, 'info')),
            'company' => WorkshopDivisions::company(),
            'photo' => $this->sitePhoto('president'),
        ]);
    }

    public function access(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.access', [
            ...$this->withSiteBackdrop($this->stageData($workshop, $locale, 'info')),
            'photo' => $this->sitePhoto('access') ?? $this->sitePhoto('hero'),
            'tiresPhone' => WorkshopDivisions::find('tires')['external']['phone'],
        ]);
    }

    /**
     * The hero photo behind every page of the site, once it has been added.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSiteBackdrop(array $data): array
    {
        $data['backdrop']['image'] = $this->sitePhoto('hero') ?? $data['backdrop']['image'];
        $data['backdrop']['character'] = null;

        return $data;
    }

    /**
     * A site photo once it has been added to public/images/yoshida, else null so the page shows its placeholder.
     */
    private function sitePhoto(string $name): ?string
    {
        $path = "images/yoshida/{$name}.webp";

        return file_exists(public_path($path)) ? asset($path) : null;
    }

    public function services(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.services-index', $this->stageData($workshop, $locale, 'services'));
    }

    public function service(Request $request, string $workshopSlug, string $serviceSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        $data = $this->stageData($workshop, $locale, 'service');
        $services = $data['serviceTypes'];
        $index = $services->search(fn (ServiceType $serviceType) => $serviceType->slug === $serviceSlug);

        abort_if($index === false, 404);

        return view('workshop.service-detail', [
            ...$data,
            'serviceType' => $services[$index],
            'serviceIndex' => $index,
            'previousService' => $services[($index - 1 + $services->count()) % $services->count()],
            'nextService' => $services[($index + 1) % $services->count()],
        ]);
    }

    public function facilities(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.facilities-index', $this->stageData($workshop, $locale, 'facilities'));
    }

    public function facility(Request $request, string $workshopSlug, int $facilityId): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        $data = $this->stageData($workshop, $locale, 'facility');
        $facilities = $data['facilities'];
        $index = $facilities->search(fn (WorkshopKnowledgeItem $item) => $item->id === $facilityId);

        abort_if($index === false, 404);

        return view('workshop.facility-detail', [
            ...$data,
            'facility' => $facilities[$index],
            'facilityIndex' => $index,
            'previousFacility' => $facilities[($index - 1 + $facilities->count()) % $facilities->count()],
            'nextFacility' => $facilities[($index + 1) % $facilities->count()],
        ]);
    }

    public function info(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.info-index', $this->stageData($workshop, $locale, 'info'));
    }

    public function staff(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.staff-index', $this->stageData($workshop, $locale, 'staff'));
    }

    public function reservationScene(Request $request, string $workshopSlug): View
    {
        [$workshop, $locale] = $this->resolveStage($request, $workshopSlug);

        return view('workshop.reservation-index', [
            ...$this->stageData($workshop, $locale, 'reservation'),
            'preselectedService' => (string) $request->query('service', ''),
        ]);
    }

    /**
     * What a signed-in member does not have to type again in the reservation form.
     *
     * @return array{name?: string, phone?: string, contact_type?: string, contact_value?: string}
     */
    private function memberPrefill(Workshop $workshop): array
    {
        $member = $this->memberBookings->signedIn($workshop);

        if (! $member) {
            return [];
        }

        return [
            'name' => (string) $member->name,
            'phone' => (string) $member->phone,
            'contact_type' => filled($member->phone) ? 'whatsapp' : 'email',
            'contact_value' => filled($member->phone) ? $member->phone : $member->email,
        ];
    }

    /**
     * @return array{0: Workshop, 1: string}
     */
    private function resolveStage(Request $request, string $workshopSlug): array
    {
        $workshop = Workshop::where('slug', $workshopSlug)->firstOrFail();

        abort_if(! $workshop->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $workshop->default_locale;

        return [$workshop, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever scene the customer
     * has walked into.
     *
     * @return array<string, mixed>
     */
    private function stageData(Workshop $workshop, string $locale, string $scene): array
    {
        $serviceTypes = $workshop->serviceTypes()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $facilities = $workshop->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['facilities', 'price_list', 'transport'])
            ->orderBy('sort_order')->get()->values();

        $labels = $this->labels($locale);
        $garage = $this->garageLabels($locale);

        return [
            'workshop' => $workshop,
            'serviceTypes' => $serviceTypes,
            'locale' => $locale,
            'scene' => $scene,
            'backdrop' => $this->sceneBackdrop($scene),
            'menuItems' => $this->stageMenu($workshop, $locale, $labels, $garage),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'garage' => $garage,
            'site' => WorkshopDivisions::siteLabels($locale),
            'serviceTerms' => self::SERVICE_TERMS[$locale],
            'wizard' => $this->wizardLabels($locale),
            'tourStops' => $this->tourStops($workshop, $serviceTypes, $locale),
            'narration' => self::NARRATION[$locale],
            'serviceNarrations' => $this->serviceNarrations($workshop, $serviceTypes, $locale),
            'staffLinks' => $this->handover->forStaff($workshop, $locale),
            'today' => now($workshop->timezone)->toDateString(),
            'prefill' => $this->memberPrefill($workshop),
            'infoItems' => $workshop->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'facilities' => $facilities,
            'sceneNarrations' => $this->sceneNarrations($workshop, $facilities, $locale),
        ];
    }

    /**
     * The stops of the advisor's guided tour: the services, a couple of them in
     * turn, then the facilities. `key` is the narrator the scene plays and
     * `spot` the part of that scene the tour draws the customer's eye to.
     *
     * @param  Collection<int, ServiceType>  $serviceTypes
     * @return list<array{key: string, url: string, line: string, spot: ?string}>
     */
    private function tourStops(Workshop $workshop, Collection $serviceTypes, string $locale): array
    {
        $tour = self::NARRATION[$locale];
        $link = fn (string $route, array $parameters = []) => route($route, ['workshopSlug' => $workshop->slug, ...$parameters, 'lang' => $locale]);

        $stops = [['key' => 'services', 'url' => $link('workshop.services'), 'line' => $tour['tour_services'], 'spot' => '.service-nav']];

        foreach ($serviceTypes->take(2) as $serviceType) {
            $stops[] = [
                'key' => 'service-'.$serviceType->slug,
                'url' => $link('workshop.service', ['serviceSlug' => $serviceType->slug]),
                'line' => str_replace(':name', $serviceType->translatedName($locale), $tour['tour_service']),
                'spot' => null,
            ];
        }

        $stops[] = ['key' => 'facilities', 'url' => $link('workshop.facilities'), 'line' => $tour['tour_facilities'], 'spot' => '.facility-grid'];

        return $stops;
    }

    /**
     * Artwork for a scene. A workshop can supply either a single picture with
     * the advisor already in it, or — preferred — a plain background plus
     * one cut-out of her (transparent PNG/WebP) that is layered in front, so
     * she can be placed clear of the panels and reused across scenes.
     *
     * @return array{image: string, focus: string, focusMobile: string, character: ?string, anchor: string, text: string, avatarImage: string, avatarZoom: string, avatarFocus: string}
     */
    private function sceneBackdrop(string $scene): array
    {
        $scenes = [
            'services' => [
                'combined' => 'images/workshop-backdrop.svg',
                'background' => 'images/workshop-backdrop.svg',
                'focus' => 'center 26%',
                'focusMobile' => '50% 14%',
                'anchor' => 'right',
                'text' => 'top',
                'avatarZoom' => '500%',
                'avatarFocus' => '52% 10%',
            ],
            'facilities' => [
                'combined' => 'images/workshop-backdrop.svg',
                'background' => 'images/workshop-backdrop.svg',
                'focus' => 'center 30%',
                'focusMobile' => '24% 18%',
                // She stands on the left of this artwork, so the scene text
                // sits low and leaves her face clear.
                'anchor' => 'left',
                'text' => 'bottom',
                'avatarZoom' => '500%',
                'avatarFocus' => '17% 11%',
            ],
            'info' => [
                // A plain terrace backdrop with her greeting cut-out layered
                // in front, standing clear on the right of the panel.
                'combined' => 'images/workshop-backdrop.svg',
                'background' => 'images/workshop-backdrop.svg',
                'character' => 'images/character/character greeting.webp',
                'focus' => 'center 38%',
                'focusMobile' => '70% 30%',
                'anchor' => 'right',
                'text' => 'bottom',
                'avatarZoom' => '300%',
                'avatarFocus' => '50% 25%',
            ],
            'staff' => [
                // A reception lounge backdrop with her grateful cut-out
                // centred, so the panel floats low and leaves her clear.
                'combined' => 'images/workshop-backdrop.svg',
                'background' => 'images/workshop-backdrop.svg',
                'character' => 'images/character/character grateful.webp',
                'focus' => 'center 30%',
                'focusMobile' => '62% 20%',
                'anchor' => 'center',
                'text' => 'top',
                'avatarZoom' => '260%',
                'avatarFocus' => '50% 12%',
            ],
            'reservation' => [
                // She stands on the left holding a tablet with a booking
                // form, echoing the wizard panel that sits beside her.
                'combined' => 'images/workshop-backdrop.svg',
                'background' => 'images/workshop-backdrop.svg',
                'character' => 'images/character/character reservation.webp',
                'focus' => 'center 30%',
                'focusMobile' => '24% 18%',
                'anchor' => 'left',
                'text' => 'bottom',
                'avatarZoom' => '300%',
                'avatarFocus' => '42% 14%',
            ],
            'garage' => [
                'combined' => 'images/workshop-backdrop.svg',
                'background' => 'images/workshop-backdrop.svg',
                'focus' => 'center 22%',
                'focusMobile' => '50% 15%',
                'anchor' => 'center',
                'text' => 'top',
                'avatarZoom' => '315%',
                'avatarFocus' => '51% 19%',
            ],
        ];

        $artwork = $scenes[['service' => 'services', 'facility' => 'facilities'][$scene] ?? $scene] ?? $scenes['garage'];

        $characterImage = $artwork['character'] ?? self::CHARACTER_IMAGE;
        $character = file_exists(public_path($characterImage)) ? $characterImage : null;
        $background = $character && file_exists(public_path($artwork['background'])) ? $artwork['background'] : null;

        if ($background === null) {
            // No separate background for this scene, so use the picture that
            // already has her in it — and do not layer her on top of herself.
            $character = null;
            $background = file_exists(public_path($artwork['combined'])) ? $artwork['combined'] : $scenes['garage']['combined'];
        }

        return [
            'image' => asset($background),
            'focus' => $artwork['focus'],
            'focusMobile' => $artwork['focusMobile'],
            'character' => $character ? asset($character) : null,
            'anchor' => $artwork['anchor'],
            'text' => $artwork['text'],
            // The avatars crop her face out of whichever picture holds her —
            // the cut-out when one is layered in, otherwise the background.
            'avatarImage' => asset($character ?? $background),
            'avatarZoom' => $artwork['avatarZoom'],
            'avatarFocus' => $artwork['avatarFocus'],
        ];
    }

    /**
     * The main menu. Sections that live on another page are marked so the
     * stage can fade out, as if the advisor were walking the customer over.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $garage
     * @return list<array{key: string, label: string, href: string, exit: bool, tour: ?string, topic: string}>
     */
    private function stageMenu(Workshop $workshop, string $locale, array $labels, array $garage): array
    {
        $servicesUrl = route('workshop.services', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
        $tour = self::NARRATION[$locale];

        $facilitiesUrl = route('workshop.facilities', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
        $infoUrl = route('workshop.info', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
        $reservationUrl = route('workshop.reservation', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
        $staffUrl = route('workshop.staff', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);

        return [
            ['key' => 'services', 'label' => $labels['services_heading'], 'href' => $servicesUrl, 'exit' => true, 'tour' => $tour['tour_services'], 'topic' => $labels['menu_services_q']],
            ['key' => 'facilities', 'label' => $labels['menu_facilities'], 'href' => $facilitiesUrl, 'exit' => true, 'tour' => $tour['tour_facilities'], 'topic' => $labels['menu_facilities_q']],
            ['key' => 'info', 'label' => $garage['menu_info'], 'href' => $infoUrl, 'exit' => true, 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'label' => $garage['reservation'], 'href' => $reservationUrl, 'exit' => true, 'tour' => $tour['tour_reservation'], 'topic' => $garage['reservation_q']],
            ['key' => 'staff', 'label' => $labels['menu_staff'], 'href' => $staffUrl, 'exit' => true, 'tour' => $tour['tour_staff'], 'topic' => $labels['menu_staff_q']],
        ];
    }

    /**
     * Intros for the facilities list and the workshop information scene, built
     * only from stored workshop data.
     *
     * @param  Collection<int, WorkshopKnowledgeItem>  $facilities
     * @return array{facilities: ?string, info: string}
     */
    private function sceneNarrations(Workshop $workshop, Collection $facilities, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($facilities->isNotEmpty()) {
            $examples = $facilities->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $facilities->count(), $examples],
                $templates[$facilities->count() === 1 ? 'facilities_one' : 'facilities']
            );
        }

        $location = collect([$workshop->city, $workshop->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':workshop', $workshop->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($workshop->opening_time) && $time($workshop->closing_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($workshop->opening_time), $time($workshop->closing_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['facilities' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, ServiceType>  $serviceTypes
     * @return array{services: ?string, service: array<string, string>}
     */
    private function serviceNarrations(Workshop $workshop, Collection $serviceTypes, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::SERVICE_TERMS[$locale];
        $money = fn ($value) => $workshop->money($value);
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');

        if ($serviceTypes->isEmpty()) {
            return ['services' => null, 'service' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $serviceTypes->count(), $money($serviceTypes->min('base_price'))],
            $templates[$serviceTypes->count() === 1 ? 'services_one' : 'services']
        );

        $services = $serviceTypes->mapWithKeys(function ($serviceType) use ($templates, $terms, $money, $sentence, $locale) {
            $parts = [$serviceType->translatedName($locale).'.', filled($serviceType->translatedDescription($locale)) ? $sentence(trim($serviceType->translatedDescription($locale))) : null];

            if ($serviceType->duration_minutes) {
                $parts[] = str_replace(':minutes', (string) $serviceType->duration_minutes, $templates['duration']);
            }

            if ($serviceType->vehicle_class) {
                $parts[] = $sentence($terms['vehicle_class'][$serviceType->vehicle_class] ?? Str::headline($serviceType->vehicle_class));
            }

            if ($serviceType->warranty_included) {
                $parts[] = $templates['warranty'];
            }

            $parts[] = str_replace(':price', $money($serviceType->base_price), $templates['price']);

            return [$serviceType->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['services' => $intro, 'service' => $services];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to', 'tagline' => 'Your AI service advisor, ready to help you book the right service.',
                'enter' => 'Enter :name', 'empty' => 'The virtual garage is being prepared. Please come back soon.',
                'loading' => 'Preparing your workshop experience…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'services' => 'Explore services', 'facilities' => 'See facilities', 'reservation' => 'Plan a reservation', 'staff' => 'Talk to staff',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'tagline' => 'AIコンシェルジュが、最適な整備のご予約をお手伝いします。',
                'enter' => ':name に入る', 'empty' => 'バーチャル工場は準備中です。しばらくしてからお越しください。',
                'loading' => '整備工場体験を準備しています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'services' => 'サービスを見る', 'facilities' => '施設を見る', 'reservation' => '予約を計画する', 'staff' => 'スタッフに相談',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'tagline' => 'AI Concierge siap membantu Anda memesan servis yang tepat.',
                'enter' => 'Masuk ke :name', 'empty' => 'Garasi virtual sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Menyiapkan pengalaman bengkel Anda…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'services' => 'Jelajahi layanan', 'facilities' => 'Lihat fasilitas', 'reservation' => 'Rencanakan reservasi', 'staff' => 'Bicara dengan staf',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(string $locale): array
    {
        return [...ReservationController::MESSAGES[$locale], 'member_discount' => MemberLabels::for($locale)['member_discount'], ...match ($locale) {
            'en' => [
                'title' => 'Reservation request', 'intro' => 'A few quick steps. Final availability is confirmed by our team.',
                'step_of' => 'Step :current of :total', 'steps' => ['Dates', 'Vehicle', 'Service', 'Your details', 'Summary'],
                'drop_off_date' => 'Drop-off date', 'pickup_date' => 'Pickup date (optional for same-day service)', 'days' => 'day(s)', 'vehicle_model' => 'Make and model', 'vehicle_model_placeholder' => 'e.g. Toyota Avanza 2019', 'license_plate' => 'License plate', 'vehicles' => 'Number of vehicles',
                'choose_service' => 'Choose a service', 'fits' => 'About :count minutes',
                'towing' => 'Add towing or vehicle pickup', 'name' => 'Full name', 'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Complaint or special request (optional)', 'special_placeholder' => 'e.g. engine knocks when cold, brakes squeal',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Submit reservation request', 'sending' => 'Sending…',
                'checking' => 'Checking availability…', 'estimated_total' => 'Estimated total', 'vehicle' => 'Vehicle', 'service' => 'Service', 'dates' => 'Dates',
                'contact' => 'Contact', 'disclaimer' => 'This is a reservation request. Final availability and prices are confirmed by workshop staff. No payment is taken now.',
                'available_instead' => 'Available for your dates instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting workshop confirmation', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_workshop' => 'Call the workshop', 'email_workshop' => 'Email the workshop', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact workshop staff.',
                'error_generic' => 'Something went wrong. Please try again or contact workshop staff.', 'select_service' => 'Please choose a service.',
                'contact_staff' => 'Contact workshop staff',
            ],
            'ja' => [
                'title' => 'ご予約リクエスト', 'intro' => 'かんたんな手順です。空き状況はスタッフが最終確認いたします。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', '車両', 'サービス', 'ご連絡先', '確認'],
                'drop_off_date' => '入庫日', 'pickup_date' => '引き取り日（当日仕上げの場合は任意）', 'days' => '日', 'vehicle_model' => 'メーカー・車種', 'vehicle_model_placeholder' => '例：トヨタ アバンザ 2019年式', 'license_plate' => 'ナンバー', 'vehicles' => '台数',
                'choose_service' => 'サービスを選ぶ', 'fits' => '約:count分',
                'towing' => 'レッカー・引き取りを追加', 'name' => 'お名前', 'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => '症状・ご要望（任意）', 'special_placeholder' => '例：冷間時にエンジン異音、ブレーキの鳴き',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => '予約リクエストを送信', 'sending' => '送信中…',
                'checking' => '空き状況を確認中…', 'estimated_total' => '概算合計', 'vehicle' => '車両', 'service' => 'サービス', 'dates' => '日程',
                'contact' => 'ご連絡先', 'disclaimer' => 'これは予約リクエストです。空き状況と料金はスタッフが最終確認します。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日程で空いているサービス：', 'done_title' => 'リクエストを受け付けました', 'reference' => '予約番号',
                'awaiting' => '整備工場の確認待ち', 'done_hint' => 'リクエストをスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_workshop' => '整備工場に電話', 'email_workshop' => '整備工場にメール', 'new_request' => '新しいリクエスト',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、スタッフにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、スタッフにご連絡ください。', 'select_service' => 'サービスを選択してください。',
                'contact_staff' => 'スタッフに連絡',
            ],
            default => [
                'title' => 'Permintaan reservasi', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim kami.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Kendaraan', 'Layanan', 'Data Anda', 'Ringkasan'],
                'drop_off_date' => 'Tanggal masuk', 'pickup_date' => 'Tanggal ambil (opsional untuk servis sehari)', 'days' => 'hari', 'vehicle_model' => 'Merek dan model', 'vehicle_model_placeholder' => 'mis. Toyota Avanza 2019', 'license_plate' => 'Nomor polisi', 'vehicles' => 'Jumlah kendaraan',
                'choose_service' => 'Pilih layanan', 'fits' => 'Sekitar :count menit',
                'towing' => 'Tambah derek atau jemput kendaraan', 'name' => 'Nama lengkap', 'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Keluhan atau permintaan khusus (opsional)', 'special_placeholder' => 'mis. mesin berisik saat dingin, rem berdecit',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim permintaan reservasi', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa ketersediaan…', 'estimated_total' => 'Perkiraan total', 'vehicle' => 'Kendaraan', 'service' => 'Layanan', 'dates' => 'Tanggal',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah permintaan reservasi. Ketersediaan dan harga final dikonfirmasi oleh staf bengkel. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Tersedia di tanggal Anda:', 'done_title' => 'Permintaan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi bengkel', 'done_hint' => 'Kirim permintaan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_workshop' => 'Telepon bengkel', 'email_workshop' => 'Email bengkel', 'new_request' => 'Permintaan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi staf bengkel.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi staf bengkel.', 'select_service' => 'Silakan pilih layanan.',
                'contact_staff' => 'Hubungi staf bengkel',
            ],
        }];
    }

    private function garageLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to your', 'garage' => 'virtual garage',
                'intro' => 'Honest work. Clear prices. Let me take care of the details.',
                'home' => 'Garage', 'explore' => 'Explore your visit', 'reservation' => 'Reservations',
                'reservation_intro' => 'Tell your AI Concierge your dates and your vehicle. We will help you find a service and submit a booking request.',
                'reservation_q' => 'I would like to book a service. Please help me check availability.',
                'start_booking' => 'Plan my visit', 'available' => 'Here to help, 24/7',
                'assistant' => 'Your virtual service advisor', 'illustration' => 'AI illustration',
                'staff_intro' => 'Need a personal touch? Send a message and we will connect you with the workshop team.',
                'empty' => 'Ask your AI Concierge for more information.', 'back' => 'Back to garage',
                'drop_off_date' => 'Drop-off date', 'pickup_date' => 'Pickup date', 'location' => 'Find us',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'services_empty' => 'Service information will be available soon. Please ask our team.',
                'menu_info' => 'Workshop information', 'info_about' => 'About the workshop', 'info_policies' => 'Policies', 'info_faq' => 'Frequently asked questions', 'info_tab_about' => 'About', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Address', 'info_hours' => 'Opening hours', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about the workshop',
                'facility_counter' => 'Facility', 'prev_facility' => 'Previous', 'next_facility' => 'Next', 'ask_facility' => 'Ask about this facility', 'back_facilities' => 'All facilities', 'open_facility' => 'Read more', 'ask_facility_q' => 'Tell me more about :name.',
                'loading' => 'Preparing your workshop experience…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'service_scene' => 'Service details', 'vehicle_counter' => 'Service', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_service' => 'Previous service', 'next_service' => 'Next service',
                'ask_service' => 'Ask about this service', 'reserve_service' => 'Request reservation', 'warranty_excluded' => 'No warranty', 'warranty' => 'Warranty',
                'duration' => 'Duration', 'minutes' => 'minutes', 'vehicle_class' => 'Vehicle class', 'towing' => 'Towing',
                'towing_yes' => 'Available', 'towing_no' => 'Not available', 'inclusions' => 'Included in the service',
                'availability_note' => 'Final availability and prices are confirmed by workshop staff.',
                'ask_service_q' => 'Tell me more about the :name.',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'garage' => 'バーチャル工場',
                'intro' => '確かな整備と明快な料金で、お車の点検をお手伝いします。',
                'home' => '工場', 'explore' => '整備のご案内', 'reservation' => 'ご予約',
                'reservation_intro' => 'ご希望の日程とお車の情報をAIコンシェルジュにお伝えください。空き確認と予約リクエストをお手伝いします。',
                'reservation_q' => 'サービスを予約したいです。空き状況を確認してください。',
                'start_booking' => '入庫を計画する', 'available' => '24時間お手伝いします',
                'assistant' => 'バーチャルコンシェルジュ', 'illustration' => 'AIイラスト',
                'staff_intro' => '整備工場のスタッフがお手伝いします。メッセージを送ってご相談ください。',
                'empty' => '詳しくはAIコンシェルジュにお尋ねください。', 'back' => '工場に戻る',
                'drop_off_date' => '入庫日', 'pickup_date' => '引き取り日', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'services_empty' => 'サービス情報は準備中です。スタッフにお尋ねください。',
                'menu_info' => '整備工場情報', 'info_about' => '整備工場について', 'info_policies' => 'ご利用規定', 'info_faq' => 'よくあるご質問', 'info_tab_about' => '整備工場について', 'info_tab_faq' => 'FAQ',
                'info_address' => '所在地', 'info_hours' => '営業時間', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => '整備工場について聞く',
                'facility_counter' => '施設', 'prev_facility' => '前へ', 'next_facility' => '次へ', 'ask_facility' => 'この施設について聞く', 'back_facilities' => '施設一覧', 'open_facility' => '詳しく見る', 'ask_facility_q' => ':name について詳しく教えてください。',
                'loading' => '整備工場体験を準備しています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'service_scene' => 'サービスのご案内', 'vehicle_counter' => 'サービス', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_service' => '前のサービス', 'next_service' => '次のサービス',
                'ask_service' => 'このサービスについて聞く', 'reserve_service' => '予約をリクエスト', 'warranty_excluded' => '保証なし', 'warranty' => '保証',
                'duration' => '作業時間', 'minutes' => '分', 'vehicle_class' => '車種区分', 'towing' => 'レッカー',
                'towing_yes' => '利用可', 'towing_no' => '利用不可', 'inclusions' => 'サービス内容',
                'availability_note' => '空き状況と料金は、整備工場スタッフが最終確認いたします。',
                'ask_service_q' => ':name について詳しく教えてください。',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'garage' => 'garasi virtual Anda',
                'intro' => 'Pengerjaan jujur. Harga jelas. Biarkan saya membantu rencana servis Anda.',
                'home' => 'Beranda', 'explore' => 'Jelajahi bengkel', 'reservation' => 'Reservasi',
                'reservation_intro' => 'Ceritakan tanggal servis dan kendaraan Anda kepada AI Concierge. Kami bantu cek ketersediaan layanan hingga pengajuan reservasi.',
                'reservation_q' => 'Saya ingin reservasi layanan. Bantu saya cek ketersediaan.',
                'start_booking' => 'Rencanakan servis', 'available' => 'Siap membantu, 24 jam',
                'assistant' => 'Service advisor virtual Anda', 'illustration' => 'Ilustrasi AI',
                'staff_intro' => 'Butuh bantuan langsung? Kirim pesan untuk terhubung dengan tim bengkel kami.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Concierge.', 'back' => 'Kembali ke garasi',
                'drop_off_date' => 'Tanggal masuk', 'pickup_date' => 'Tanggal ambil', 'location' => 'Lokasi bengkel',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'services_empty' => 'Informasi layanan segera tersedia. Silakan tanyakan kepada staf kami.',
                'menu_info' => 'Informasi bengkel', 'info_about' => 'Tentang bengkel', 'info_policies' => 'Kebijakan', 'info_faq' => 'Pertanyaan yang sering diajukan', 'info_tab_about' => 'Tentang', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Alamat', 'info_hours' => 'Jam buka', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya tentang bengkel',
                'facility_counter' => 'Fasilitas', 'prev_facility' => 'Sebelumnya', 'next_facility' => 'Berikutnya', 'ask_facility' => 'Tanya tentang fasilitas ini', 'back_facilities' => 'Semua fasilitas', 'open_facility' => 'Selengkapnya', 'ask_facility_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Menyiapkan pengalaman bengkel Anda…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'service_scene' => 'Detail layanan', 'vehicle_counter' => 'Layanan', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_service' => 'Layanan sebelumnya', 'next_service' => 'Layanan berikutnya',
                'ask_service' => 'Tanya tentang layanan ini', 'reserve_service' => 'Ajukan reservasi', 'warranty_excluded' => 'Tanpa garansi', 'warranty' => 'Garansi',
                'duration' => 'Durasi', 'minutes' => 'menit', 'vehicle_class' => 'Kelas kendaraan', 'towing' => 'Derek',
                'towing_yes' => 'Tersedia', 'towing_no' => 'Tidak tersedia', 'inclusions' => 'Termasuk dalam layanan',
                'availability_note' => 'Ketersediaan dan harga final dikonfirmasi oleh staf bengkel.',
                'ask_service_q' => 'Ceritakan lebih banyak tentang :name.',
            ],
        };
    }

    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'price_suffix' => '',
                'ask_ai' => 'Ask the AI Concierge',
                'services_heading' => 'Services',
                'chat_heading' => 'AI Concierge',
                'chat_subtitle' => 'Ask about services, facilities, or your visit — available 24/7.',
                'chat_placeholder' => 'Type your message…',
                'chat_send' => 'Send',
                'chat_mic' => 'Speak to the advisor',
                'chat_mic_listening' => 'Listening… tap to stop',
                'chat_mic_denied' => 'Microphone access is blocked. Please allow it in your browser settings.',
                'chat_mic_nospeech' => "I didn't catch that. Please try again.",
                'chat_open' => 'Chat with AI Concierge',
                'chat_close' => 'Close conversation',
                'chat_intro' => "Hi! I'm the AI Concierge here. Ask me about services, facilities, or anything about your visit.",
                'warranty_included' => 'Warranty included',
                'chat_service_only' => 'No warranty',
                'chat_day' => 'day(s)',
                'chat_no_availability' => 'No availability for those dates.',
                'chat_booking_received' => 'Booking request received',
                'chat_reference' => 'Ref',
                'handed_over' => 'A staff member has joined this conversation and will reply shortly.',
                'chat_status_sent' => 'Message sent',
                'chat_status_waiting' => 'Waiting for staff reply',
                'chat_status_replied' => 'Staff has replied',
                'chat_draft_title' => 'Unsaved message',
                'chat_draft_body' => 'This message has not been sent. Keep it as a draft?',
                'chat_draft_keep' => 'Keep typing',
                'chat_draft_discard' => 'Discard draft',
                'thinking' => 'Thinking…',
                'chat_error' => "I'm having trouble responding right now. Please try again or contact workshop staff.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
                'view_details' => 'View details',
                'service_details_question' => 'Show me more details and photos of :service',
                'book_now' => 'Book this service',
                'chat_hide_details' => 'Hide details',
                'chat_form_title' => 'Reserve :service',
                'chat_form_confirm' => 'Confirm reservation',
                'chat_showing_services' => 'Showing :count services',
                'chat_showing_facilities' => 'Showing :count facilities',
                'chat_open_map' => 'Open in Maps',
                'chat_directions' => 'Directions',
                'chat_getting_there' => 'Getting here',
                'chat_showing_photos' => ':count photos',
                'chat_hours' => 'Open',
                'chat_photo_prev' => 'Previous photo',
                'chat_photo_next' => 'Next photo',
                'chat_photo_close' => 'Close photo',
                'quick_facilities_q' => 'Show me the workshop facilities with photos.',
                'quick_location' => 'Location & map',
                'quick_location_q' => 'Where is the workshop and how do I get there?',
                'quick_menu' => 'Price list',
                'quick_menu_q' => 'Can I see the price list?',
                'chat_close_showcase' => 'Close',
                'quick_cheapest' => 'Cheapest service', 'quick_cheapest_q' => 'Which is the cheapest service available?',
                'quick_family' => 'For an older car', 'quick_family_q' => 'Which service suits an older car best?',
                'quick_view' => 'Regular maintenance', 'quick_view_q' => 'Which service do you recommend for regular maintenance?',
                'quick_pick_dates' => 'Pick dates', 'quick_tour' => 'Tour with the AI',
                'picker_title' => 'When would you like to bring your vehicle in?', 'picker_message' => 'I would like to bring my vehicle in:',
                'picker_pick_start' => 'Choose your drop-off date', 'picker_pick_end' => 'Choose your pickup date (tap the same day for same-day service)', 'picker_continue' => 'Continue',
                'tour_progress' => 'Tour :current / :total', 'tour_next' => 'Skip ›', 'tour_stop' => 'Stop',
                'tour_done' => 'That was a quick look around. When would you like to bring your vehicle in?',
                'chat_add_calendar' => 'Add to calendar', 'chat_share' => 'Share', 'chat_share_text' => 'My visit at :workshop: :service, :in → :out. Ref :ref',
                'chat_new' => 'New chat',
                'chat_new_title' => 'Start a new chat?',
                'chat_new_body' => 'The current conversation will be cleared from this screen and the advisor will start fresh.',
                'chat_new_confirm' => 'Start new chat',
                'chat_new_keep' => 'Keep this chat',
                'menu_heading' => 'Start here',
                'menu_services' => 'Find a service',
                'menu_services_q' => 'I would like to see the available services.',
                'menu_facilities' => 'Workshop facilities',
                'menu_facilities_q' => 'What facilities does the workshop have?',
                'menu_policies' => 'Policies & FAQ',
                'menu_policies_q' => 'What are the drop-off, pickup, and cancellation policies?',
                'menu_staff' => 'Talk to staff',
                'menu_staff_q' => 'I would like to speak with workshop staff.',
            ],
            'ja' => [
                'from' => '',
                'price_suffix' => '〜',
                'ask_ai' => 'AIコンシェルジュに聞く',
                'services_heading' => 'サービス',
                'chat_heading' => 'AIコンシェルジュ',
                'chat_subtitle' => 'サービス・設備・ご入庫について24時間いつでもお尋ねください。',
                'chat_placeholder' => 'メッセージを入力…',
                'chat_send' => '送信',
                'chat_mic' => 'コンシェルジュに話しかける',
                'chat_mic_listening' => '聞いています… タップで停止',
                'chat_mic_denied' => 'マイクへのアクセスがブロックされています。ブラウザの設定で許可してください。',
                'chat_mic_nospeech' => '聞き取れませんでした。もう一度お試しください。',
                'chat_open' => 'AIコンシェルジュに相談',
                'chat_close' => '会話を閉じる',
                'chat_intro' => 'こんにちは。AIコンシェルジュです。サービスや設備、ご入庫について何でもお尋ねください。',
                'warranty_included' => '保証付き',
                'chat_service_only' => '保証なし',
                'chat_day' => '日',
                'chat_no_availability' => 'ご希望の日程には空きがありません。',
                'chat_booking_received' => '予約リクエストを受け付けました',
                'chat_reference' => '受付番号',
                'handed_over' => 'スタッフがこの会話に参加しました。まもなく返信いたします。',
                'chat_status_sent' => 'メッセージを送信しました',
                'chat_status_waiting' => 'スタッフの返信を待っています',
                'chat_status_replied' => 'スタッフが返信しました',
                'chat_draft_title' => '未送信のメッセージ',
                'chat_draft_body' => 'このメッセージはまだ送信されていません。下書きとして残しますか？',
                'chat_draft_keep' => '入力を続ける',
                'chat_draft_discard' => '下書きを破棄',
                'thinking' => '入力中…',
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、スタッフにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
                'view_details' => '詳細を見る',
                'service_details_question' => ':service の詳細と写真を見せてください',
                'book_now' => 'このサービスを予約',
                'chat_hide_details' => '詳細を閉じる',
                'chat_form_title' => ':service を予約',
                'chat_form_confirm' => '予約を確定する',
                'chat_showing_services' => ':count 件のサービスをご案内中',
                'chat_showing_facilities' => ':count 件の施設をご案内中',
                'chat_open_map' => 'マップで開く',
                'chat_directions' => '経路を見る',
                'chat_getting_there' => 'アクセス',
                'chat_showing_photos' => ':count 枚の写真',
                'chat_hours' => '営業時間',
                'chat_photo_prev' => '前の写真',
                'chat_photo_next' => '次の写真',
                'chat_photo_close' => '写真を閉じる',
                'quick_facilities_q' => '整備工場の施設を写真付きで見せてください。',
                'quick_location' => '所在地・地図',
                'quick_location_q' => '整備工場はどこにあり、どう行けばいいですか？',
                'quick_menu' => '料金表',
                'quick_menu_q' => '料金表を見られますか？',
                'chat_close_showcase' => '閉じる',
                'quick_cheapest' => '一番安いサービス', 'quick_cheapest_q' => '空いている中で一番安いサービスはどれですか？',
                'quick_family' => '古いお車向け', 'quick_family_q' => '古い車に一番合うサービスはどれですか？',
                'quick_view' => '定期点検', 'quick_view_q' => '定期点検にはどのサービスがおすすめですか？',
                'quick_pick_dates' => '日程を選ぶ', 'quick_tour' => 'AIと工場ツアー',
                'picker_title' => 'いつご入庫されますか？', 'picker_message' => '入庫を希望します：',
                'picker_pick_start' => '入庫日を選択', 'picker_pick_end' => '引き取り日を選択（当日仕上げは同じ日をタップ）', 'picker_continue' => '次へ',
                'tour_progress' => 'ツアー :current / :total', 'tour_next' => 'スキップ ›', 'tour_stop' => '終了',
                'tour_done' => '工場のご案内は以上です。ご入庫はいつ頃をご希望ですか？',
                'chat_add_calendar' => 'カレンダーに追加', 'chat_share' => '共有', 'chat_share_text' => ':workshop のご入庫：:service、:in → :out。予約番号 :ref',
                'chat_new' => '新しいチャット',
                'chat_new_title' => '新しいチャットを始めますか？',
                'chat_new_body' => '現在の会話はこの画面から消去され、コンシェルジュが最初からご案内します。',
                'chat_new_confirm' => '新しく始める',
                'chat_new_keep' => 'このまま続ける',
                'menu_heading' => 'ここから始める',
                'menu_services' => 'サービスを探す',
                'menu_services_q' => '空いているサービスを見せてください。',
                'menu_facilities' => '整備工場施設',
                'menu_facilities_q' => '整備工場にはどんな施設がありますか？',
                'menu_policies' => '規定・よくある質問',
                'menu_policies_q' => '入庫、引き取り、キャンセルのポリシーを教えてください。',
                'menu_staff' => 'スタッフに相談',
                'menu_staff_q' => '整備工場スタッフと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'price_suffix' => '',
                'ask_ai' => 'Tanya AI Concierge',
                'services_heading' => 'Layanan',
                'chat_heading' => 'AI Concierge',
                'chat_subtitle' => 'Tanyakan layanan, fasilitas, atau rencana servis Anda — siap 24 jam.',
                'chat_placeholder' => 'Tulis pesan Anda…',
                'chat_send' => 'Kirim',
                'chat_mic' => 'Bicara dengan advisor',
                'chat_mic_listening' => 'Mendengarkan… ketuk untuk berhenti',
                'chat_mic_denied' => 'Akses mikrofon diblokir. Mohon izinkan di pengaturan browser Anda.',
                'chat_mic_nospeech' => 'Suara tidak terdengar. Silakan coba lagi.',
                'chat_open' => 'Chat dengan AI Concierge',
                'chat_close' => 'Tutup percakapan',
                'chat_intro' => 'Halo! Saya AI Concierge bengkel ini. Tanyakan apa saja soal layanan, fasilitas, atau rencana servis Anda.',
                'warranty_included' => 'Termasuk garansi',
                'chat_service_only' => 'Tanpa garansi',
                'chat_day' => 'hari',
                'chat_no_availability' => 'Tidak ada layanan tersedia untuk tanggal tersebut.',
                'chat_booking_received' => 'Permintaan reservasi diterima',
                'chat_reference' => 'Ref',
                'handed_over' => 'Staf kami telah bergabung dalam percakapan ini dan akan segera membalas.',
                'chat_status_sent' => 'Pesan terkirim',
                'chat_status_waiting' => 'Menunggu balasan staf',
                'chat_status_replied' => 'Staf telah membalas',
                'chat_draft_title' => 'Pesan belum dikirim',
                'chat_draft_body' => 'Pesan ini belum dikirim. Simpan sebagai draft?',
                'chat_draft_keep' => 'Lanjut mengetik',
                'chat_draft_discard' => 'Buang draft',
                'thinking' => 'Sedang mengetik…',
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi staf bengkel.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
                'view_details' => 'Lihat detail',
                'service_details_question' => 'Tunjukkan detail dan foto lengkap :service',
                'book_now' => 'Pesan layanan ini',
                'chat_hide_details' => 'Tutup detail',
                'chat_form_title' => 'Pesan :service',
                'chat_form_confirm' => 'Konfirmasi reservasi',
                'chat_showing_services' => 'Menampilkan :count layanan',
                'chat_showing_facilities' => 'Menampilkan :count fasilitas',
                'chat_open_map' => 'Buka di Maps',
                'chat_directions' => 'Petunjuk arah',
                'chat_getting_there' => 'Cara menuju ke sini',
                'chat_showing_photos' => ':count foto',
                'chat_hours' => 'Buka',
                'chat_photo_prev' => 'Foto sebelumnya',
                'chat_photo_next' => 'Foto berikutnya',
                'chat_photo_close' => 'Tutup foto',
                'quick_facilities_q' => 'Tunjukkan fasilitas bengkel beserta fotonya.',
                'quick_location' => 'Lokasi & peta',
                'quick_location_q' => 'Di mana lokasi bengkel dan bagaimana cara ke sana?',
                'quick_menu' => 'Daftar harga',
                'quick_menu_q' => 'Boleh saya lihat daftar harganya?',
                'chat_close_showcase' => 'Tutup',
                'quick_cheapest' => 'Layanan termurah', 'quick_cheapest_q' => 'Layanan mana yang paling murah dan tersedia?',
                'quick_family' => 'Untuk mobil tua', 'quick_family_q' => 'Layanan mana yang paling cocok untuk mobil tua?',
                'quick_view' => 'Perawatan rutin', 'quick_view_q' => 'Layanan mana yang disarankan untuk perawatan rutin?',
                'quick_pick_dates' => 'Pilih tanggal', 'quick_tour' => 'Tur bersama AI',
                'picker_title' => 'Kapan Anda ingin servis?', 'picker_message' => 'Saya ingin servis:',
                'picker_pick_start' => 'Pilih tanggal masuk', 'picker_pick_end' => 'Pilih tanggal ambil (ketuk hari yang sama untuk servis sehari)', 'picker_continue' => 'Lanjut',
                'tour_progress' => 'Tur :current / :total', 'tour_next' => 'Lewati ›', 'tour_stop' => 'Berhenti',
                'tour_done' => 'Itu tadi sekilas bengkel kami. Kapan Anda ingin servis?',
                'chat_add_calendar' => 'Tambahkan ke kalender', 'chat_share' => 'Bagikan', 'chat_share_text' => 'Servis di :workshop: :service, :in → :out. Ref :ref',
                'chat_new' => 'Chat baru',
                'chat_new_title' => 'Mulai chat baru?',
                'chat_new_body' => 'Percakapan saat ini akan dihapus dari layar dan advisor akan memulai dari awal.',
                'chat_new_confirm' => 'Mulai chat baru',
                'chat_new_keep' => 'Tetap di chat ini',
                'menu_heading' => 'Mulai dari sini',
                'menu_services' => 'Cari layanan',
                'menu_services_q' => 'Saya ingin melihat pilihan layanan yang tersedia.',
                'menu_facilities' => 'Fasilitas bengkel',
                'menu_facilities_q' => 'Apa saja fasilitas yang tersedia di bengkel ini?',
                'menu_policies' => 'Kebijakan & FAQ',
                'menu_policies_q' => 'Apa kebijakan masuk, pengambilan kendaraan, dan pembatalan?',
                'menu_staff' => 'Bicara dengan staf',
                'menu_staff_q' => 'Saya ingin bicara dengan staf bengkel.',
            ],
        };
    }
}
