<?php

namespace App\Services\Advisor;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\ServiceType;
use App\Models\Workshop;
use App\Models\WorkshopKnowledgeItem;
use App\Notifications\NewHandoverRequest;
use App\Services\Reservation\MemberBookings;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes the AI Advisor's tools against this workshop's controlled data.
 * Every workshop fact, service, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class WorkshopAdvisorTools
{
    private const MAX_RECOMMENDATIONS = 6;

    private const MAX_CARD_PHOTOS = 4;

    private const MAX_FACILITY_CARDS = 6;

    private const FACILITY_CATEGORIES = ['facilities', 'price_list', 'transport'];

    public function __construct(
        private readonly Workshop $workshop,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
        private readonly MemberBookings $memberBookings = new MemberBookings,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via AdvisorService — see topic in that class).
     */
    public static function definitions(): array
    {
        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the workshop\'s approved knowledge base for facts about the workshop: policies, facilities, price lists, transport, and FAQs. Always use this instead of answering workshop-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the customer question, e.g. "late pickup" or "warranty"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'facilities', 'policies', 'price_list', 'transport', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'show_facility_photos',
                    'description' => 'Show the workshop\'s facilities as tappable photo cards on screen. Use this when the customer wants to SEE a facility or the facilities (e.g. "tunjukkan foto ruang tunggu", "show me the service bay", "can I see the lounge", "lihat fasilitas"). Leave query empty to show every facility that has a photo. Call search_knowledge as well if the customer also asks a factual question about it.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Optional keywords for one facility, e.g. "lounge", "service bay", "paint booth". Empty shows all.'],
                        ],
                    ],
                ],
                [
                    'name' => 'show_location',
                    'description' => 'Show the workshop\'s address and a map card with "open in maps" and "get directions" buttons, plus how to get there and any pickup service. Use this when the customer asks where the workshop is, how to get there, wants to see the location or the map, or the address.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
                [
                    'name' => 'show_service_photos',
                    'description' => 'Show a photo gallery of services. Use this when the customer wants to SEE the work — the engine bay, the brakes, the bodywork — without naming one service, or wants a photo set of one service. Pass image_tag (e.g. "engine", "brake", "tire", "bodywork", "interior") and/or service_type_slug.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'image_tag' => ['type' => 'string', 'description' => 'What to see, e.g. "engine", "brake", "tire"'],
                            'service_type_slug' => ['type' => 'string', 'description' => 'Optional: only this service'],
                        ],
                    ],
                ],
                [
                    'name' => 'show_price_list',
                    'description' => 'Show the price list card with opening hours, photos and the packages and parts the workshop listed. Use this when the customer wants to see a price list, packages or part prices. Leave query empty for every price list entry.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Optional keywords, e.g. "oil", "tire"'],
                        ],
                    ],
                ],
                [
                    'name' => 'search_services',
                    'description' => 'Search service types with free slots on the customer\'s dates, with real-time availability and total price already checked. Use this whenever a customer describes what they want (dates, vehicle class, budget) rather than naming one service by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'drop_off_date' => ['type' => 'string', 'description' => 'Drop-off date, YYYY-MM-DD'],
                            'pickup_date' => ['type' => 'string', 'description' => 'Pickup date, YYYY-MM-DD. Leave out for same-day service.'],
                            'vehicle_class' => ['type' => 'string', 'description' => 'e.g. city car, MPV, SUV, truck — optional preference'],
                        ],
                        'required' => ['drop_off_date'],
                    ],
                ],
                [
                    'name' => 'recommend_services',
                    'description' => 'Show the workshop\'s services as tappable cards, with photos, details and an indicative starting price. Use this when the customer asks for a recommendation or what services exist and has NOT given dates yet (e.g. "rekomendasi layanan", "what services do you have", "best service for an old car"). Do not ask for dates first — show the cards, then let the customer tap a card to reserve.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vehicle_class' => ['type' => 'string', 'description' => 'e.g. city car, MPV, SUV, truck — optional preference'],
                        ],
                    ],
                ],
                [
                    'name' => 'open_booking_form',
                    'description' => 'Open a reservation form inside the chat for one service, pre-filled with whatever the customer already told you (dates, vehicle, plate, name, phone). Use this when the customer wants to book but is missing details, instead of asking for each detail one by one — they fill the rest by tapping. If the customer already gave service, date, vehicle, plate, name and phone in text, call create_booking_request instead.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type_slug' => ['type' => 'string'],
                            'drop_off_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the customer said it'],
                            'pickup_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the customer said it'],
                            'vehicle_model' => ['type' => 'string', 'description' => 'Make and model, e.g. Toyota Avanza'],
                            'license_plate' => ['type' => 'string', 'description' => 'License plate, e.g. B 1234 XYZ'],
                            'customer_name' => ['type' => 'string'],
                            'customer_phone' => ['type' => 'string'],
                        ],
                        'required' => ['service_type_slug'],
                    ],
                ],
                [
                    'name' => 'ask_visit_details',
                    'description' => 'Show a calendar and a vehicle counter in the chat so the customer picks their drop-off and pickup dates and number of vehicles by tapping. Use this whenever you need dates that the customer has not given yet — instead of asking for them in text. Optionally name the service they are choosing for. Their choice arrives as their next message.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type_slug' => ['type' => 'string', 'description' => 'Optional: the service the customer is choosing dates for'],
                            'drop_off_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the customer already said it'],
                            'pickup_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD, only if the customer already said it'],
                        ],
                    ],
                ],
                [
                    'name' => 'start_tour',
                    'description' => 'Start a guided tour of the workshop, narrated by the advisor, that walks the customer through the services and facilities. Use it when the customer asks to be shown around, asks for a tour, or does not know where to start.',
                    'parameters' => ['type' => 'object', 'properties' => new \stdClass],
                ],
                [
                    'name' => 'get_service_detail',
                    'description' => 'Get full details and photos for one service type by its slug (from a previous search_services result). Use this when the customer asks to see more about, or see photos of, a specific service.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "engine", "brake", "tire"'],
                        ],
                        'required' => ['service_type_slug'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one service type over specific dates. ALWAYS call this before confirming a price or telling a customer a service is available — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type_slug' => ['type' => 'string'],
                            'drop_off_date' => ['type' => 'string'],
                            'pickup_date' => ['type' => 'string'],
                            'vehicles' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of vehicles, default 1'],
                            'towing' => ['type' => 'boolean'],
                        ],
                        'required' => ['service_type_slug', 'drop_off_date'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create a reservation request after the customer confirms the service and dates and you have their vehicle, license plate, name and phone. This re-checks availability before booking. It does not charge payment — it creates a pending reservation for the workshop to confirm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'service_type_slug' => ['type' => 'string'],
                            'drop_off_date' => ['type' => 'string'],
                            'pickup_date' => ['type' => 'string'],
                            'vehicle_model' => ['type' => 'string', 'description' => 'Make and model, e.g. Toyota Avanza'],
                            'license_plate' => ['type' => 'string', 'description' => 'License plate, e.g. B 1234 XYZ'],
                            'vehicles' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of vehicles, default 1'],
                            'towing' => ['type' => 'boolean'],
                            'customer_name' => ['type' => 'string'],
                            'customer_email' => ['type' => 'string'],
                            'customer_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['service_type_slug', 'drop_off_date', 'vehicle_model', 'license_plate', 'customer_name', 'customer_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human workshop staff member. Use this for special requests, complaints, group bookings, negotiated rates, unusual cancellations, payment problems, or any question you cannot answer confidently from the available tools. Always write a clear summary so staff do not need to ask the customer to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'group_booking', 'negotiated_rate', 'unusual_cancellation', 'payment_issue', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the customer wants and the relevant context gathered so far, written for a staff member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null, miss?: bool}
     */
    public function dispatch(string $name, array $input): array
    {
        try {
            return $this->run($name, $input);
        } catch (\Throwable $e) {
            Log::warning('Advisor tool call failed', ['tool' => $name, 'input' => $input, 'error' => $e->getMessage()]);

            return [
                'text' => "The {$name} call was rejected because its arguments were missing or invalid (dates must be YYYY-MM-DD, slugs must come from the service list). Do not guess: call it again with corrected arguments, or ask the customer for what is missing.",
                'ui' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    private function run(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'show_facility_photos' => $this->showFacilityPhotos($input),
            'show_location' => $this->showLocation(),
            'show_service_photos' => $this->showServicePhotos($input),
            'show_price_list' => $this->showMenu($input),
            'search_services' => $this->searchServices($input),
            'recommend_services' => $this->recommendServices($input),
            'open_booking_form' => $this->openBookingForm($input),
            'ask_visit_details' => $this->askVisitDetails($input),
            'start_tour' => $this->startTour(),
            'get_service_detail' => $this->getServiceDetail($input),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $items = $this->matchingKnowledge((string) ($input['query'] ?? ''), $input['category'] ?? null)->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the customer you will check with the team, or call request_human_handover.',
                'ui' => null,
                'miss' => true,
            ];
        }

        $results = $items->map(fn (WorkshopKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    /**
     * Active knowledge entries whose title, body or tags mention the query.
     *
     * @return Collection<int, WorkshopKnowledgeItem>
     */
    private function matchingKnowledge(string $query, ?string $category = null): Collection
    {
        $query = trim($query);
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter(fn ($word) => mb_strlen($word) >= 3);

        return $this->workshop->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderBy('sort_order')
            ->get()
            ->filter(function (WorkshopKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->values();
    }

    private function showFacilityPhotos(array $input): array
    {
        $facilities = $this->matchingKnowledge((string) ($input['query'] ?? ''))
            ->filter(fn (WorkshopKnowledgeItem $item) => in_array($item->category, self::FACILITY_CATEGORIES, true) && $item->photoUrls() !== [])
            ->take(self::MAX_FACILITY_CARDS)
            ->map(fn (WorkshopKnowledgeItem $item) => [
                'id' => $item->id,
                'title' => $item->translatedTitle($this->locale),
                'body' => $item->translatedBody($this->locale),
                'image_url' => $item->photoUrls()[0],
                'images' => array_slice($item->photoUrls(), 0, self::MAX_CARD_PHOTOS + 1),
                'hours' => $item->openingHours(),
                'icon' => $item->iconName(),
            ])
            ->values()
            ->all();

        if ($facilities === []) {
            return [
                'text' => 'No facility photo matches that. Say so honestly, answer from search_knowledge if there is a text entry, or offer to show all facilities.',
                'ui' => null,
                'miss' => true,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'The facility photo cards are now on screen. Comment on them in one or two short sentences; do not list every facility again and do not invent hours or fees.',
                'facilities' => array_map(fn (array $facility) => ['title' => $facility['title'], 'body' => $facility['body']], $facilities),
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'facility_results', 'facilities' => $facilities],
        ];
    }

    private function showLocation(): array
    {
        $workshop = $this->workshop;
        $hasPoint = $workshop->latitude !== null && $workshop->longitude !== null;
        $address = collect([$workshop->address, $workshop->city, $workshop->country])->filter()->implode(', ');

        if (! $hasPoint && $address === '') {
            return [
                'text' => 'The workshop has no address or map point on file. Say so honestly and call request_human_handover if the customer needs directions.',
                'ui' => null,
            ];
        }

        $target = $hasPoint ? (float) $workshop->latitude.','.(float) $workshop->longitude : $address;
        $getting = $this->matchingKnowledge('', WorkshopKnowledgeItem::CATEGORY_TRANSPORT)
            ->take(3)
            ->map(fn (WorkshopKnowledgeItem $item) => [
                'title' => $item->translatedTitle($this->locale),
                'body' => $item->translatedBody($this->locale),
            ])
            ->values()
            ->all();

        $card = [
            'name' => $workshop->name,
            'address' => $address,
            'latitude' => $hasPoint ? (float) $workshop->latitude : null,
            'longitude' => $hasPoint ? (float) $workshop->longitude : null,
            'maps_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($target),
            'directions_url' => 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($target),
            'phone' => $workshop->phone,
            'getting_there' => $getting,
        ];

        return [
            'text' => json_encode([
                'note' => 'A location card with the address and map buttons is now on screen. Comment on it in one short sentence; use only the facts below for how to get there.',
                'address' => $address,
                'getting_there' => $getting,
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'location', 'location' => $card],
        ];
    }

    private function showServicePhotos(array $input): array
    {
        $tag = filled($input['image_tag'] ?? null) ? Str::lower(trim((string) $input['image_tag'])) : null;
        $slug = filled($input['service_type_slug'] ?? null) ? (string) $input['service_type_slug'] : null;

        $services = $slug
            ? $this->matchServiceTypes($slug)->load('images')
            : $this->workshop->serviceTypes()->where('is_active', true)->orderBy('sort_order')->with('images')->get();

        $photos = $services->flatMap(function (ServiceType $serviceType) use ($tag, $slug) {
            $images = $serviceType->images->filter(fn ($image) => filled($image->image_source));

            if ($tag) {
                $images = $images->filter(fn ($image) => collect($image->tags ?? [])->contains(fn ($t) => Str::lower($t) === $tag));
            } elseif (! $slug) {
                $images = $images->take(1);
            }

            return $images->map(fn ($image) => [
                'url' => $image->image_source,
                'alt' => $image->alt_text ?: $serviceType->translatedName($this->locale),
                'tags' => $image->tags ?? [],
                'service_name' => $serviceType->translatedName($this->locale),
                'service_type_slug' => $serviceType->slug,
            ]);
        })->take(12)->values()->all();

        if ($photos === []) {
            return [
                'text' => $services->isEmpty() && $slug
                    ? 'No service matches that name. The services are: '.$this->serviceDirectory().'. Call show_service_photos again with one of these slugs, or recommend_services.'
                    : 'No service photo matches that. Say so honestly and offer to show a service\'s details with get_service_detail or recommend_services.',
                'ui' => null,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'The service photo gallery is now on screen. Comment on it in one short sentence; do not list the photos.',
                'services' => collect($photos)->pluck('service_name')->unique()->values()->all(),
                'image_tag' => $tag,
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'service_photos', 'image_tag' => $tag, 'photos' => $photos],
        ];
    }

    private function showMenu(array $input): array
    {
        $entries = $this->matchingKnowledge((string) ($input['query'] ?? ''), WorkshopKnowledgeItem::CATEGORY_PRICE_LIST)
            ->take(3)
            ->map(fn (WorkshopKnowledgeItem $item) => [
                'id' => $item->id,
                'title' => $item->translatedTitle($this->locale),
                'body' => $item->translatedBody($this->locale),
                'hours' => $item->openingHours(),
                'menu' => $item->menuEntries(),
                'images' => array_slice($item->photoUrls(), 0, self::MAX_CARD_PHOTOS + 1),
            ])
            ->values()
            ->all();

        if ($entries === []) {
            return [
                'text' => 'No price_list entry matches that. Say so honestly, or answer from search_knowledge, or call request_human_handover.',
                'ui' => null,
                'miss' => true,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'The price_list card is now on screen with the hours, photos and menu. Comment on it in one short sentence; use only these facts and never invent dishes, prices or hours.',
                'price_list' => array_map(fn (array $entry) => ['title' => $entry['title'], 'body' => $entry['body'], 'hours' => $entry['hours'], 'menu' => $entry['menu']], $entries),
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'price_list', 'entries' => $entries],
        ];
    }

    private function searchServices(array $input): array
    {
        [$dropOffDate, $pickupDate, $days] = $this->parseVisit($input['drop_off_date'], $input['pickup_date'] ?? null);
        $vehicleClass = $input['vehicle_class'] ?? null;

        if ($days < 1) {
            return ['text' => 'pickup_date cannot be before drop_off_date.', 'ui' => null];
        }

        $candidates = $this->workshop->serviceTypes()
            ->where('is_active', true)
            ->get()
            ->when($vehicleClass, fn ($c) => $c->filter(fn (ServiceType $rt) => $rt->vehicle_class === $vehicleClass));

        $matches = [];
        foreach ($candidates as $serviceType) {
            $visit = $this->reservations->quote($serviceType, $dropOffDate, $pickupDate);
            if (! $visit) {
                continue;
            }

            $matches[] = [
                ...$this->serviceCard($serviceType),
                'days' => $days,
                'total_price' => $visit['service_total'],
                'min_available_units' => $visit['min_available_units'],
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No service type has a free slot on every day of that visit. Tell the customer honestly and offer to check different dates, or call request_human_handover if they want to wait-list.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode(array_map($this->withoutPhotos(...), $matches), JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'service_results',
                'drop_off_date' => $input['drop_off_date'],
                'pickup_date' => $input['pickup_date'],
                'services' => $matches,
            ],
        ];
    }

    private function recommendServices(array $input): array
    {
        $vehicleClass = $input['vehicle_class'] ?? null;

        $services = $this->workshop->serviceTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->when($vehicleClass, fn ($c) => $c->filter(fn (ServiceType $rt) => $rt->vehicle_class === $vehicleClass))
            ->take(self::MAX_RECOMMENDATIONS)
            ->map(fn (ServiceType $rt) => $this->serviceCard($rt))
            ->values()
            ->all();

        if ($services === []) {
            return [
                'text' => 'No service type matches that preference. Say so honestly and offer to show every service, or call request_human_handover.',
                'ui' => null,
            ];
        }

        return [
            'text' => json_encode([
                'note' => 'starting_price is an indicative price only. The cards are on screen with a reserve button; the customer picks dates in the reservation form, which shows the exact total. Do not list the services again.',
                'services' => array_map($this->withoutPhotos(...), $services),
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'service_results', 'drop_off_date' => null, 'pickup_date' => null, 'services' => $services],
        ];
    }

    private function askVisitDetails(array $input): array
    {
        $serviceType = filled($input['service_type_slug'] ?? null) ? $this->findServiceType($input['service_type_slug']) : null;

        $values = array_filter([
            'drop_off_date' => $this->validDate($input['drop_off_date'] ?? null),
            'pickup_date' => $this->validDate($input['pickup_date'] ?? null),
        ], fn ($value) => $value !== null);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A calendar and vehicle counter are now on screen. Tell the customer in one short sentence to pick their dates and number of vehicles. Do not ask for them in text: their choice arrives as their next message.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'visit_picker', 'service' => $serviceType?->is_active ? $this->serviceCard($serviceType) : null, 'values' => $values],
        ];
    }

    private function startTour(): array
    {
        return [
            'text' => json_encode(['ok' => true, 'message' => 'The guided tour is starting on screen. Say one short, warm sentence to introduce it; the tour narrates the rest.'], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'tour_start'],
        ];
    }

    private function openBookingForm(array $input): array
    {
        $serviceType = $this->findServiceType($input['service_type_slug']);
        if (! $serviceType || ! $serviceType->is_active) {
            return ['text' => 'Service type not found.', 'ui' => null];
        }

        $form = [
            'service' => $this->serviceCard($serviceType),
            'values' => array_filter([
                'drop_off_date' => $this->validDate($input['drop_off_date'] ?? null),
                'pickup_date' => $this->validDate($input['pickup_date'] ?? null),
                'vehicle_model' => filled($input['vehicle_model'] ?? null) ? (string) $input['vehicle_model'] : null,
                'license_plate' => filled($input['license_plate'] ?? null) ? (string) $input['license_plate'] : null,
                'customer_name' => filled($input['customer_name'] ?? null) ? (string) $input['customer_name'] : null,
                'contact_value' => filled($input['customer_phone'] ?? null) ? (string) $input['customer_phone'] : null,
            ], fn ($value) => $value !== null),
        ];

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A reservation form for this service is now on screen, pre-filled with what the customer told you. Tell them in one short sentence to check the details and tap the confirm button. Do not ask for the details again in text.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_form', ...$form],
        ];
    }

    private function getServiceDetail(array $input): array
    {
        $serviceType = $this->findServiceType($input['service_type_slug']) ?? $this->singleMatchingServiceType($input['service_type_slug']);
        if (! $serviceType) {
            return ['text' => 'Service type not found. The services are: '.$this->serviceDirectory().'.', 'ui' => null];
        }

        $images = $serviceType->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'service_type_slug' => $serviceType->slug,
            'name' => $serviceType->translatedName($this->locale),
            'description' => $serviceType->translatedDescription($this->locale),
            'duration_minutes' => $serviceType->duration_minutes,
            'vehicle_class' => $serviceType->vehicle_class,
            'warranty_included' => $serviceType->warranty_included,
            'towing_available' => $serviceType->towing_available,
            'towing_price' => $serviceType->towing_price,
            'inclusions' => $serviceType->inclusions,
            'starting_price' => $serviceType->base_price,
            'currency' => $this->workshop->currency,
            'note' => 'starting_price is indicative only — always call check_availability for the exact price on real dates.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'service_detail', 'service' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $serviceType = $this->findServiceType($input['service_type_slug']);
        if (! $serviceType) {
            return ['text' => 'Service type not found.', 'ui' => null];
        }

        [$dropOffDate, $pickupDate, $days] = $this->parseVisit($input['drop_off_date'], $input['pickup_date'] ?? null);
        if ($days < 1) {
            return ['text' => 'pickup_date cannot be before drop_off_date.', 'ui' => null];
        }

        $towing = (bool) ($input['towing'] ?? false);
        $vehicles = max(1, (int) ($input['vehicles'] ?? 1));
        $member = $this->memberBookings->signedIn($this->workshop);
        $visit = $this->reservations->quote($serviceType, $dropOffDate, $pickupDate, $vehicles, $towing, discountPercent: $this->memberBookings->discountFor($this->workshop, $member));

        if (! $visit) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'service_type_slug' => $serviceType->slug,
                    'message' => 'Not every day in this range has availability for this service type.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'service_type_slug' => $serviceType->slug, 'service' => $this->serviceCard($serviceType)],
            ];
        }

        $result = [
            'available' => true,
            'service_type_slug' => $serviceType->slug,
            'name' => $serviceType->translatedName($this->locale),
            'drop_off_date' => $dropOffDate->toDateString(),
            'pickup_date' => $pickupDate->toDateString(),
            'days' => $days,
            'service_total' => $visit['service_total'],
            'towing_total' => $visit['towing_total'],
            'member_discount_percent' => $visit['discount_percent'],
            'discount_amount' => $visit['discount_amount'],
            'grand_total' => $visit['grand_total'],
            'currency' => $this->workshop->currency,
            'vehicles' => $vehicles,
            'min_available_units' => $visit['min_available_units'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result, 'service' => $this->serviceCard($serviceType)],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $serviceType = $this->findServiceType($input['service_type_slug']);
        if (! $serviceType) {
            return ['text' => 'Service type not found.', 'ui' => null];
        }

        [$dropOffDate, $pickupDate, $days] = $this->parseVisit($input['drop_off_date'], $input['pickup_date'] ?? null);
        if ($days < 1) {
            return ['text' => 'pickup_date cannot be before drop_off_date.', 'ui' => null];
        }

        if ($dropOffDate->isBefore(now($this->workshop->timezone)->startOfDay()) || $days > ReservationService::MAX_DAYS) {
            return ['text' => 'Drop-off cannot be in the past and a visit is limited to '.ReservationService::MAX_DAYS.' days. Ask the customer for valid dates, or call request_human_handover for longer visits.', 'ui' => null];
        }

        if (! preg_match(ReservationService::PHONE_PATTERN, (string) $input['customer_phone'])) {
            return ['text' => 'That phone number is not valid (it needs 6 to 20 digits). Ask the customer for a valid phone or WhatsApp number, or call open_booking_form so they can enter it themselves.', 'ui' => null];
        }

        if (filled($input['customer_email'] ?? null) && ! filter_var($input['customer_email'], FILTER_VALIDATE_EMAIL)) {
            return ['text' => 'That email address is not valid. The email is optional: leave customer_email out, or ask the customer to correct it.', 'ui' => null];
        }

        if (blank($input['vehicle_model'] ?? null) || blank($input['license_plate'] ?? null)) {
            return ['text' => 'The vehicle and its license plate are needed for a booking. Ask the customer for them, or call open_booking_form so they can enter them themselves.', 'ui' => null];
        }

        $booking = $this->reservations->createRequest($this->workshop, $serviceType, [
            'drop_off_date' => $dropOffDate,
            'pickup_date' => $pickupDate,
            'vehicle_model' => Str::limit((string) $input['vehicle_model'], 100, ''),
            'license_plate' => Str::limit((string) $input['license_plate'], 20, ''),
            'vehicles' => max(1, (int) ($input['vehicles'] ?? 1)),
            'towing' => (bool) ($input['towing'] ?? false),
            'customer_name' => Str::limit((string) $input['customer_name'], 100, ''),
            'customer_email' => filled($input['customer_email'] ?? null) ? Str::limit((string) $input['customer_email'], 120, '') : null,
            'customer_phone' => $input['customer_phone'],
            'notes' => filled($input['notes'] ?? null) ? Str::limit((string) $input['notes'], 500, '') : null,
            'member_id' => $this->memberBookings->signedIn($this->workshop)?->id,
        ], $this->conversation);

        if (! $booking) {
            return [
                'text' => 'Service is no longer available for these exact dates — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $confirmation = self::bookingConfirmation($booking, $serviceType, $this->locale, $this->workshop->currency);

        return [
            'text' => json_encode($confirmation['booking'], JSON_UNESCAPED_UNICODE),
            'ui' => $confirmation,
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        $handover = HandoverRequest::updateOrCreate(
            ['conversation_id' => $this->conversation->id, 'status' => HandoverRequest::STATUS_OPEN],
            ['reason' => $reason, 'summary' => $summary],
        );

        if ($handover->wasRecentlyCreated) {
            $this->reservations->notifyStaff($this->workshop, new NewHandoverRequest($handover));
        }

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A staff member has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    // --- helpers ---

    /**
     * The service as the chat renders it: enough to show a card and its details
     * without another round trip. Prices here are indicative only.
     *
     * @return array<string, mixed>
     */
    private function serviceCard(ServiceType $serviceType): array
    {
        $images = $serviceType->images->take(self::MAX_CARD_PHOTOS)->map(fn ($img) => [
            'url' => $img->image_source,
            'alt' => $img->alt_text,
        ])->values()->all();

        return [
            'service_type_slug' => $serviceType->slug,
            'name' => $serviceType->translatedName($this->locale),
            'description' => $serviceType->translatedDescription($this->locale),
            'duration_minutes' => $serviceType->duration_minutes,
            'vehicle_class' => $serviceType->vehicle_class,
            'inclusions' => $serviceType->inclusions ?? [],
            'warranty_included' => $serviceType->warranty_included,
            'towing_available' => $serviceType->towing_available,
            'starting_price' => (float) $serviceType->base_price,
            'currency' => $this->workshop->currency,
            'thumbnail_url' => $images[0]['url'] ?? null,
            'images' => $images,
        ];
    }

    /**
     * Drops what only the screen needs, so the model is not fed photo URLs.
     *
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function withoutPhotos(array $card): array
    {
        unset($card['images'], $card['thumbnail_url']);

        return $card;
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = CarbonImmutable::parse($value);

        return $date->isBefore(now($this->workshop->timezone)->startOfDay()) ? null : $date->toDateString();
    }

    /**
     * What the chat shows once a booking request exists, whether the AI or the
     * customer's own taps created it.
     *
     * @return array{type: string, booking: array<string, mixed>}
     */
    public static function bookingConfirmation(Booking $booking, ServiceType $serviceType, string $locale, string $currency): array
    {
        return [
            'type' => 'booking_confirmation',
            'booking' => [
                'booking_reference' => $booking->reference,
                'service_type_slug' => $serviceType->slug,
                'name' => $serviceType->translatedName($locale),
                'drop_off_date' => $booking->drop_off_date->toDateString(),
                'pickup_date' => $booking->pickup_date->toDateString(),
                'days' => $booking->days(),
                'vehicle_model' => $booking->vehicle_model,
                'license_plate' => $booking->license_plate,
                'vehicles' => $booking->vehicle_count,
                'total_price' => (float) $booking->total_price,
                'currency' => $currency,
                'status' => 'pending_confirmation',
            ],
        ];
    }

    private function findServiceType(string $slug): ?ServiceType
    {
        return $this->workshop->serviceTypes()->where('is_active', true)->where('slug', $slug)->first();
    }

    /**
     * The active services a customer's wording points at: the exact slug, else any
     * service whose slug or name (in any language) contains the term or one of
     * its words — so "tune up" finds "Tune Up Mesin".
     *
     * @return Collection<int, ServiceType>
     */
    private function matchServiceTypes(string $term): Collection
    {
        $services = $this->workshop->serviceTypes()->where('is_active', true)->orderBy('sort_order')->get();
        $term = Str::lower(trim($term));

        $exact = $services->where('slug', $term);
        if ($exact->isNotEmpty()) {
            return $exact->values();
        }

        $words = collect(preg_split('/[\s_-]+/u', $term))
            ->filter(fn ($word) => mb_strlen($word) >= 3 && ! in_array($word, ['service', 'services', 'layanan', 'the', 'type'], true))
            ->values();

        return $services->filter(function (ServiceType $service) use ($term, $words) {
            $haystack = Str::lower(str_replace('-', ' ', $service->slug).' '.$service->name.' '.collect($service->translations ?? [])->pluck('name')->filter()->implode(' '));

            return Str::contains($haystack, str_replace('-', ' ', $term))
                || $words->contains(fn ($word) => Str::contains($haystack, $word));
        })->values();
    }

    /**
     * The one active service a customer's free text names. A full service name (in any
     * language) wins; otherwise at least two words of a service's name must be
     * found, typos tolerated, and one of them must belong to that service alone
     * — so "oli" or "mesin" never pick a service by themselves.
     */
    public function serviceNamedIn(string $text): ?ServiceType
    {
        $services = $this->workshop->serviceTypes()->where('is_active', true)->orderBy('sort_order')->get();
        $text = Str::lower($text);

        $named = $services->filter(fn (ServiceType $service) => $this->serviceNames($service)->contains(fn (string $name) => Str::contains($text, $name)));
        if ($named->count() === 1) {
            return $named->first();
        }

        $customerWords = $this->nameWords($text);
        $wordsByService = $services->mapWithKeys(fn (ServiceType $service) => [$service->id => $this->serviceNames($service)->flatMap(fn (string $name) => $this->nameWords($name))->unique()->values()]);
        $servicesPerWord = $wordsByService->flatten()->countBy();

        $scored = $services->map(function (ServiceType $service) use ($customerWords, $wordsByService, $servicesPerWord) {
            $found = $wordsByService[$service->id]->filter(fn (string $word) => $customerWords->contains(fn (string $customerWord) => $this->isSameWord($customerWord, $word)));

            return [
                'service' => $service,
                'score' => $found->count(),
                'distinctive' => $found->contains(fn (string $word) => $servicesPerWord[$word] === 1),
            ];
        })->filter(fn (array $row) => $row['score'] >= 2 && $row['distinctive'])->sortByDesc('score')->values();

        if ($scored->isEmpty() || ($scored->count() > 1 && $scored[0]['score'] === $scored[1]['score'])) {
            return null;
        }

        return $scored[0]['service'];
    }

    /**
     * @return Collection<int, string>
     */
    private function serviceNames(ServiceType $service): Collection
    {
        return collect([$service->name, ...collect($service->translations ?? [])->pluck('name')->all()])
            ->filter()
            ->map(fn ($name) => Str::lower((string) $name))
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private function nameWords(string $text): Collection
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, ['service', 'services', 'layanan', 'the', 'type', 'with'], true))
            ->values();
    }

    private function isSameWord(string $customerWord, string $serviceWord): bool
    {
        return $customerWord === $serviceWord
            || (mb_strlen($customerWord) >= 5 && mb_strlen($serviceWord) >= 5 && levenshtein($customerWord, $serviceWord) <= 1);
    }

    private function singleMatchingServiceType(string $term): ?ServiceType
    {
        $matches = $this->matchServiceTypes($term);

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function serviceDirectory(): string
    {
        return $this->workshop->serviceTypes()->where('is_active', true)->orderBy('sort_order')->get()
            ->map(fn (ServiceType $service) => "{$service->name} (slug: {$service->slug})")
            ->implode(', ');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function parseVisit(string $dropOffDate, ?string $pickupDate): array
    {
        $in = CarbonImmutable::parse($dropOffDate)->startOfDay();
        $out = filled($pickupDate) ? CarbonImmutable::parse($pickupDate)->startOfDay() : $in;

        return [$in, $out, (int) $in->diffInDays($out) + 1];
    }
}
