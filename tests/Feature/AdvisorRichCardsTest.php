<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\Workshop;
use App\Services\Advisor\WorkshopAdvisorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvisorRichCardsTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private ServiceType $deluxe;

    private Conversation $conversation;

    private CarbonImmutable $dropOffDate;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.local_llm.base_url' => 'http://llm.test',
            'services.local_llm.api_key' => 'test-key',
            'services.local_llm.model' => 'test-model',
        ]);

        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en', 'timezone' => 'Asia/Makassar']);
        $this->deluxe = $this->workshop->serviceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'description' => 'A calm service.', 'base_price' => 1000000,
            'inclusions' => ['wifi', 'bathtub'], 'warranty_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->deluxe->images()->create(['image_url' => 'https://img.test/deluxe.jpg', 'alt_text' => 'Deluxe bedroom', 'sort_order' => 0]);
        $this->workshop->serviceTypes()->create([
            'name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2000000, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->conversation = Conversation::create(['workshop_id' => $this->workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $this->dropOffDate = CarbonImmutable::now('Asia/Makassar')->startOfDay()->addDays(5);

        foreach ([0, 1, 2] as $offset) {
            ServiceInventory::create(['service_type_id' => $this->deluxe->id, 'service_date' => $this->dropOffDate->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }
    }

    private function tools(): WorkshopAdvisorTools
    {
        return new WorkshopAdvisorTools($this->workshop, $this->conversation, 'en');
    }

    public function test_showing_facilities_returns_photo_cards_only_for_entries_with_a_photo(): void
    {
        $this->workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Swimming Pool', 'body' => 'Open 7am to 9pm.', 'tags' => ['pool'], 'image_url' => 'https://img.test/pool.jpg', 'is_active' => true, 'sort_order' => 0]);
        $this->workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool Bar', 'body' => 'Drinks by the pool.', 'tags' => ['pool'], 'is_active' => true, 'sort_order' => 1]);
        $this->workshop->knowledgeItems()->create(['category' => 'policies', 'title' => 'Pool rules', 'body' => 'No diving.', 'image_url' => 'https://img.test/rules.jpg', 'is_active' => true, 'sort_order' => 2]);

        $result = $this->tools()->dispatch('show_facility_photos', ['query' => 'pool']);

        $this->assertSame('facility_results', $result['ui']['type']);
        $this->assertCount(1, $result['ui']['facilities']);
        $this->assertSame('Swimming Pool', $result['ui']['facilities'][0]['title']);
        $this->assertSame('https://img.test/pool.jpg', $result['ui']['facilities'][0]['image_url']);
        $this->assertStringNotContainsString('img.test', $result['text']);
    }

    public function test_showing_facilities_without_a_matching_photo_shows_no_cards(): void
    {
        $result = $this->tools()->dispatch('show_facility_photos', ['query' => 'helipad']);

        $this->assertNull($result['ui']);
    }

    public function test_a_facility_card_carries_its_whole_gallery_and_hours(): void
    {
        $this->workshop->knowledgeItems()->create([
            'category' => 'facilities', 'title' => 'Swimming Pool', 'body' => 'Open daily.', 'image_url' => 'https://img.test/pool.jpg',
            'images' => ['https://img.test/pool.jpg', 'https://img.test/pool-day.jpg'], 'details' => ['hours' => '07:00–19:00', 'menu' => []],
            'is_active' => true, 'sort_order' => 0,
        ]);

        $facility = $this->tools()->dispatch('show_facility_photos', [])['ui']['facilities'][0];

        $this->assertSame(['https://img.test/pool.jpg', 'https://img.test/pool-day.jpg'], $facility['images']);
        $this->assertSame('07:00–19:00', $facility['hours']);
    }

    public function test_the_location_card_has_map_links_and_how_to_get_there(): void
    {
        $this->workshop->update(['address' => 'Jl. Pantai 8', 'city' => 'Nusa Dua', 'country' => 'Indonesia', 'latitude' => -8.8008, 'longitude' => 115.2317]);
        $this->workshop->knowledgeItems()->create(['category' => 'transport', 'title' => 'Airport transfer', 'body' => '30 minutes from the airport.', 'is_active' => true, 'sort_order' => 0]);

        $result = $this->tools()->dispatch('show_location', []);

        $this->assertSame('location', $result['ui']['type']);
        $this->assertSame('Jl. Pantai 8, Nusa Dua, Indonesia', $result['ui']['location']['address']);
        $this->assertStringContainsString('destination=-8.8008%2C115.2317', $result['ui']['location']['directions_url']);
        $this->assertSame('Airport transfer', $result['ui']['location']['getting_there'][0]['title']);
    }

    public function test_the_location_card_is_skipped_when_the_workshop_has_no_address_or_point(): void
    {
        $this->workshop->update(['address' => null, 'city' => null, 'country' => null, 'latitude' => null, 'longitude' => null]);

        $this->assertNull($this->tools()->dispatch('show_location', [])['ui']);
    }

    public function test_service_photos_can_be_filtered_by_what_the_customer_wants_to_see(): void
    {
        $this->deluxe->images()->create(['image_url' => 'https://img.test/deluxe-bath.jpg', 'tags' => ['bathroom'], 'alt_text' => 'Deluxe bathroom', 'sort_order' => 1]);

        $result = $this->tools()->dispatch('show_service_photos', ['image_tag' => 'Bathroom']);

        $this->assertSame('service_photos', $result['ui']['type']);
        $this->assertCount(1, $result['ui']['photos']);
        $this->assertSame('https://img.test/deluxe-bath.jpg', $result['ui']['photos'][0]['url']);
        $this->assertSame('deluxe-king', $result['ui']['photos'][0]['service_type_slug']);
        $this->assertStringNotContainsString('img.test', $result['text']);
        $this->assertNull($this->tools()->dispatch('show_service_photos', ['image_tag' => 'helipad'])['ui']);
    }

    public function test_service_photos_find_a_service_by_a_loose_name_instead_of_its_exact_slug(): void
    {
        $suite = $this->workshop->serviceTypes()->where('slug', 'family-suite')->first();
        $suite->images()->create(['image_url' => 'https://img.test/suite.jpg', 'alt_text' => 'Suite living area', 'sort_order' => 0]);

        foreach (['suite', 'suite service', 'Family Suite', 'family-suite'] as $term) {
            $result = $this->tools()->dispatch('show_service_photos', ['service_type_slug' => $term]);

            $this->assertSame('service_photos', $result['ui']['type'], $term);
            $this->assertSame(['family-suite'], array_values(array_unique(array_column($result['ui']['photos'], 'service_type_slug'))), $term);
        }

        $unknown = $this->tools()->dispatch('show_service_photos', ['service_type_slug' => 'castle']);

        $this->assertNull($unknown['ui']);
        $this->assertStringContainsString('family-suite', $unknown['text']);
    }

    public function test_the_menu_card_lists_the_dishes_and_hours_the_workshop_entered(): void
    {
        $this->workshop->knowledgeItems()->create([
            'category' => 'price_list', 'title' => 'Breakfast', 'body' => 'Buffet at Samudra.', 'image_url' => 'https://img.test/breakfast.jpg',
            'details' => ['hours' => '06:30–10:30', 'menu' => [['name' => 'Bubur ayam', 'description' => 'With chicken', 'price' => 'Rp 40.000'], ['name' => '']]],
            'is_active' => true, 'sort_order' => 0,
        ]);

        $entry = $this->tools()->dispatch('show_price_list', [])['ui']['entries'][0];

        $this->assertSame('06:30–10:30', $entry['hours']);
        $this->assertSame([['name' => 'Bubur ayam', 'description' => 'With chicken', 'price' => 'Rp 40.000']], $entry['menu']);
        $this->assertSame(['https://img.test/breakfast.jpg'], $entry['images']);
    }

    public function test_quick_replies_after_the_new_cards_point_to_the_next_thing_to_see(): void
    {
        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'show_location', 'arguments' => '{}']]]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'Here is where we are.'], 'finish_reason' => 'stop']]]),
        ]);
        $this->workshop->update(['address' => 'Jl. Pantai 8', 'latitude' => -8.8, 'longitude' => 115.2]);

        $token = $this->postJson('/demo/advisor/start', ['locale' => 'en'])->json('customer_token');

        $this->postJson('/demo/advisor/message', ['customer_token' => $token, 'message' => 'Where is the workshop?'])
            ->assertOk()
            ->assertJsonPath('message.ui_payload.0.type', 'location')
            ->assertJsonPath('message.quick_replies', ['facilities', 'services', 'tour']);
    }

    public function test_a_recommendation_shows_service_cards_without_asking_for_dates(): void
    {
        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'recommend_services', 'arguments' => '{}']]]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'These two suit most customers.'], 'finish_reason' => 'stop']]]),
        ]);

        $token = $this->postJson('/demo/advisor/start', ['locale' => 'en'])->json('customer_token');

        $response = $this->postJson('/demo/advisor/message', ['customer_token' => $token, 'message' => 'Recommend a service'])
            ->assertOk()
            ->assertJsonPath('message.ui_payload.0.type', 'service_results')
            ->assertJsonPath('message.ui_payload.0.drop_off_date', null)
            ->assertJsonPath('message.ui_payload.0.services.0.service_type_slug', 'deluxe-king')
            ->assertJsonPath('message.ui_payload.0.services.0.starting_price', 1000000)
            ->assertJsonPath('message.ui_payload.0.services.0.thumbnail_url', 'https://img.test/deluxe.jpg')
            ->assertJsonPath('message.ui_payload.0.services.0.inclusions', ['wifi', 'bathtub'])
            ->assertJsonPath('message.ui_payload.0.services.1.service_type_slug', 'family-suite');

        $this->assertCount(2, $response->json('message.ui_payload.0.services'));

        Http::assertSent(function (Request $request) {
            $tool = collect($request['messages'])->firstWhere('role', 'tool');

            return $tool !== null && str_contains($tool['content'], 'Deluxe King') && ! str_contains($tool['content'], 'img.test');
        });
    }

    public function test_a_recommendation_only_offers_services_for_that_vehicle_class(): void
    {
        $this->workshop->serviceTypes()->where('slug', 'family-suite')->update(['vehicle_class' => 'suv']);

        $result = $this->tools()->dispatch('recommend_services', ['vehicle_class' => 'suv']);

        $this->assertSame(['family-suite'], array_column($result['ui']['services'], 'service_type_slug'));

        $this->assertNull($this->tools()->dispatch('recommend_services', ['vehicle_class' => 'truck'])['ui']);
    }

    public function test_the_booking_form_opens_prefilled_and_drops_dates_in_the_past(): void
    {
        $result = $this->tools()->dispatch('open_booking_form', [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => $this->dropOffDate->addDay()->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 111 222',
        ]);

        $this->assertSame('booking_form', $result['ui']['type']);
        $this->assertSame('deluxe-king', $result['ui']['service']['service_type_slug']);
        $this->assertSame([
            'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => $this->dropOffDate->addDay()->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Ayu', 'contact_value' => '+62 811 111 222',
        ], $result['ui']['values']);

        $stale = $this->tools()->dispatch('open_booking_form', ['service_type_slug' => 'deluxe-king', 'drop_off_date' => '2020-01-01']);
        $this->assertSame([], $stale['ui']['values']);

        $this->assertNull($this->tools()->dispatch('open_booking_form', ['service_type_slug' => 'no-such-service'])['ui']);
    }

    public function test_the_ai_will_not_book_a_visit_that_starts_in_the_past(): void
    {
        $result = $this->tools()->dispatch('create_booking_request', [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => '2020-01-01', 'pickup_date' => '2020-01-03',
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 111 222',
        ]);

        $this->assertNull($result['ui']);
        $this->assertSame(0, Booking::count());
    }

    public function test_the_ai_rejects_an_invalid_phone_but_never_needs_an_email(): void
    {
        $input = [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => $this->dropOffDate->addDay()->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Miftah', 'customer_phone' => '08865',
        ];

        $this->assertNull($this->tools()->dispatch('create_booking_request', $input)['ui']);
        $this->assertSame(0, Booking::count());

        $this->assertNull($this->tools()->dispatch('create_booking_request', [...$input, 'customer_phone' => '081234567890', 'customer_email' => 'not-an-email'])['ui']);

        $booked = $this->tools()->dispatch('create_booking_request', [...$input, 'customer_phone' => '081234567890']);

        $this->assertSame('booking_confirmation', $booked['ui']['type']);
        $this->assertNull(Booking::firstOrFail()->customer_email);
    }

    public function test_a_reply_that_only_promises_an_action_is_sent_back_to_call_the_tool(): void
    {
        $call = ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'open_booking_form', 'arguments' => json_encode(['service_type_slug' => 'deluxe-king'])]];

        Http::fake(['llm.test/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => "I'll proceed with creating a reservation for Miftah."], 'finish_reason' => 'stop']]])
            ->push(['choices' => [['message' => ['content' => null, 'tool_calls' => [$call]], 'finish_reason' => 'tool_calls']]])
            ->push(['choices' => [['message' => ['content' => 'Please check the details and tap confirm.'], 'finish_reason' => 'stop']]]),
        ]);

        $token = $this->postJson('/demo/advisor/start', ['locale' => 'en'])->json('customer_token');

        $this->postJson('/demo/advisor/message', ['customer_token' => $token, 'message' => 'miftah, 08123456789'])
            ->assertOk()
            ->assertJsonPath('message.ui_payload.0.type', 'booking_form')
            ->assertJsonPath('message.content', 'Please check the details and tap confirm.');

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => collect($request['messages'])->contains(fn ($m) => str_contains((string) ($m['content'] ?? ''), 'called no tool')));
    }

    public function test_the_visit_picker_opens_prefilled_and_ignores_dates_in_the_past(): void
    {
        $result = $this->tools()->dispatch('ask_visit_details', [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => '2020-01-03',
        ]);

        $this->assertSame('visit_picker', $result['ui']['type']);
        $this->assertSame('deluxe-king', $result['ui']['service']['service_type_slug']);
        $this->assertSame(['drop_off_date' => $this->dropOffDate->toDateString()], $result['ui']['values']);

        $open = $this->tools()->dispatch('ask_visit_details', []);
        $this->assertNull($open['ui']['service']);
        $this->assertSame([], $open['ui']['values']);
    }

    public function test_the_advisor_can_start_the_guided_tour(): void
    {
        $this->assertSame('tour_start', $this->tools()->dispatch('start_tour', [])['ui']['type']);

        $names = collect(WorkshopAdvisorTools::definitions())->pluck('function.name');
        $this->assertTrue($names->contains('ask_visit_details') && $names->contains('start_tour'));
    }

    public function test_replies_offer_tappable_answers_that_fit_what_was_shown(): void
    {
        $token = $this->conversation->customer_token;
        $reply = fn (array $payload, ?string $content = 'Here you go.') => $this->conversation->messages()->create(['role' => ConversationMessage::ROLE_ASSISTANT, 'content' => $content, 'ui_payload' => $payload ?: null]);
        $history = fn () => collect($this->getJson('/demo/advisor/history?customer_token='.$token)->assertOk()->json('messages'))->last()['quick_replies'];

        $reply([['type' => 'service_results', 'drop_off_date' => null, 'pickup_date' => null, 'services' => []]]);
        $this->assertSame(['pick_dates', 'cheapest', 'family', 'view'], $history());

        $reply([['type' => 'service_results', 'drop_off_date' => '2030-01-01', 'pickup_date' => '2030-01-03', 'services' => []]]);
        $this->assertSame(['cheapest', 'family', 'view'], $history());

        $reply([['type' => 'booking_form', 'service' => [], 'values' => []]]);
        $this->assertSame([], $history());

        $reply([['type' => 'booking_confirmation', 'booking' => []]]);
        $this->assertSame(['facilities', 'policies', 'tour'], $history());

        $reply([], 'Happy to help!');
        $this->assertSame(['services', 'facilities', 'location', 'policies', 'tour'], $history());

        $reply([['type' => 'handover']], null);
        $this->assertSame([], $history());
    }

    public function test_availability_cards_carry_the_service_so_they_can_open_the_form(): void
    {
        $result = $this->tools()->dispatch('check_availability', [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => $this->dropOffDate->addDays(2)->toDateString(),
        ]);

        $this->assertSame('deluxe-king', $result['ui']['service']['service_type_slug']);
        $this->assertSame(1000000.0, $result['ui']['quote']['grand_total']);
    }

    public function test_a_booking_made_with_the_in_chat_form_is_kept_in_the_conversation(): void
    {
        $response = $this->postJson('/demo/reservation', [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => $this->dropOffDate->addDay()->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'vehicles' => 1, 'locale' => 'en', 'customer_name' => 'Ayu', 'contact_type' => 'whatsapp', 'contact_value' => '+62 811 111 222',
            'customer_token' => $this->conversation->customer_token, 'via_chat' => true,
        ])->assertCreated()
            ->assertJsonPath('confirmation.type', 'booking_confirmation')
            ->assertJsonPath('confirmation.booking.total_price', 1000000)
            ->assertJsonPath('confirmation.booking.days', 2);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $response->json('confirmation.booking.booking_reference'));
        $this->assertSame($this->conversation->id, $booking->conversation_id);

        $saved = $this->conversation->messages()->firstOrFail();
        $this->assertSame(ConversationMessage::ROLE_ASSISTANT, $saved->role);
        $this->assertSame('booking_confirmation', $saved->ui_payload[0]['type']);

        $this->getJson('/demo/advisor/history?customer_token='.$this->conversation->customer_token)
            ->assertOk()
            ->assertJsonPath('messages.0.ui_payload.0.booking.booking_reference', $booking->reference);
    }

    public function test_a_booking_from_the_reservation_page_leaves_the_conversation_alone(): void
    {
        $this->postJson('/demo/reservation', [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate->toDateString(), 'pickup_date' => $this->dropOffDate->addDay()->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'vehicles' => 1, 'locale' => 'en', 'customer_name' => 'Ayu', 'contact_type' => 'whatsapp', 'contact_value' => '+62 811 111 222',
            'customer_token' => $this->conversation->customer_token,
        ])->assertCreated()->assertJsonPath('confirmation', null);

        $this->assertSame(0, $this->conversation->messages()->count());
    }
}
