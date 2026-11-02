<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Workshop;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private ServiceType $deluxe;

    private ServiceType $suite;

    private string $dropOffDate;

    private string $pickupDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'front@demo.test',
        ]);

        $this->deluxe = $this->workshop->serviceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000,
            'towing_available' => true, 'towing_price' => 250000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->suite = $this->workshop->serviceTypes()->create([
            'name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2000000,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $this->dropOffDate = $today->addDays(10)->toDateString();
        $this->pickupDate = $today->addDays(13)->toDateString();

        foreach ([$this->deluxe->id => [2, 1000000], $this->suite->id => [1, 2000000]] as $serviceTypeId => [$units, $price]) {
            foreach (range(10, 13) as $offset) {
                ServiceInventory::create([
                    'service_type_id' => $serviceTypeId, 'service_date' => $today->addDays($offset)->toDateString(),
                    'total_units' => $units, 'booked_units' => 0, 'price' => $price,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function visit(array $overrides = []): array
    {
        return [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $this->dropOffDate, 'pickup_date' => $this->pickupDate,
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'vehicles' => 1, 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->visit([
            'customer_name' => 'John Tan', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'special_request' => 'High floor', ...$overrides,
        ]);
    }

    public function test_quote_is_a_flat_price_per_vehicle_however_many_days_it_takes(): void
    {
        $this->postJson('/demo/reservation/quote', $this->visit(['vehicles' => 2, 'towing' => true]))
            ->assertOk()
            ->assertJson([
                'available' => true, 'days' => 4, 'service_total' => 2000000, 'towing_total' => 500000,
                'grand_total' => 2500000, 'currency' => 'IDR',
            ]);
    }

    public function test_quote_rejects_invalid_dates_in_the_customer_language(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->visit(['drop_off_date' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.drop_off_date.0', 'Tanggal masuk tidak boleh di masa lalu.');

        $this->postJson('/demo/reservation/quote', $this->visit(['pickup_date' => CarbonImmutable::parse($this->dropOffDate)->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.pickup_date.0', 'Pickup cannot be before drop-off.');
    }

    public function test_quote_rejects_visits_longer_than_the_limit(): void
    {
        $tooLong = CarbonImmutable::parse($this->dropOffDate)->addDays(31)->toDateString();

        $this->postJson('/demo/reservation/quote', $this->visit(['pickup_date' => $tooLong]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pickup_date');
    }

    public function test_same_day_service_needs_no_pickup_date(): void
    {
        $this->postJson('/demo/reservation/quote', $this->visit(['pickup_date' => null]))
            ->assertOk()
            ->assertJson(['available' => true, 'days' => 1, 'grand_total' => 1000000]);
    }

    public function test_closed_days_inside_a_visit_do_not_block_it_but_a_closed_pickup_day_does(): void
    {
        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        ServiceInventory::where('service_type_id', $this->deluxe->id)->whereDate('service_date', $today->addDays(11)->toDateString())->delete();

        $this->postJson('/demo/reservation/quote', $this->visit())->assertOk()->assertJsonPath('days', 4);

        $this->postJson('/demo/reservation/quote', $this->visit(['pickup_date' => $today->addDays(11)->toDateString()]))->assertUnprocessable();
    }

    public function test_a_booking_needs_the_vehicle_and_its_license_plate(): void
    {
        $this->postJson('/demo/reservation', $this->request(['vehicle_model' => '', 'license_plate' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_model', 'license_plate']);
    }

    public function test_quote_rejects_unknown_and_inactive_services(): void
    {
        $this->deluxe->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->visit())->assertUnprocessable()->assertJsonValidationErrors('service_type_slug');
        $this->postJson('/demo/reservation/quote', $this->visit(['service_type_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('service_type_slug');
    }

    public function test_unavailable_service_offers_alternatives_free_on_those_dates(): void
    {
        ServiceInventory::where('service_type_id', $this->deluxe->id)->update(['booked_units' => 2]);

        $this->postJson('/demo/reservation/quote', $this->visit())
            ->assertUnprocessable()
            ->assertJsonPath('errors.service_type_slug.0', 'This service is not available for those dates.')
            ->assertJsonPath('alternatives.0.slug', 'family-suite')
            ->assertJsonPath('alternatives.0.total', 2000000);
    }

    public function test_quote_asks_for_more_units_than_are_free_when_booking_several_services(): void
    {
        $this->postJson('/demo/reservation/quote', $this->visit(['service_type_slug' => 'family-suite', 'vehicles' => 2, 'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.service_type_slug.0', 'This service is not available for those dates.');
    }

    public function test_submitting_creates_a_pending_request_holds_inventory_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['workshop_id' => $this->workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['vehicles' => 2, 'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_token' => $conversation->customer_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 2000000)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^BK-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame(2, $booking->vehicle_count);
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->customer_phone);
        $this->assertNull($booking->customer_email);
        $this->assertSame('High floor', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame([2, 2, 2, 2], ServiceInventory::where('service_type_id', $this->deluxe->id)->orderBy('service_date')->pluck('booked_units')->all());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: John Tan', $message);
        $this->assertStringContainsString('Services: 2', $message);
        $this->assertStringContainsString('Service Type: Deluxe King', $message);
        $this->assertStringContainsString('Special Request: High floor', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:front@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'john@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('john@example.com', $booking->customer_email);
        $this->assertNull($booking->customer_phone);
    }

    public function test_the_last_free_service_can_only_be_requested_once(): void
    {
        $this->postJson('/demo/reservation', $this->request(['service_type_slug' => 'family-suite']))->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['service_type_slug' => 'family-suite', 'customer_name' => 'Late Customer']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.service_type_slug.0', 'This service is not available for those dates.');

        $this->assertSame(1, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_workshops_reject_reservations(): void
    {
        Workshop::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->visit())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->visit())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->visit())->assertTooManyRequests();
    }

    public function test_cancelling_a_multi_service_booking_releases_every_held_unit(): void
    {
        $this->postJson('/demo/reservation', $this->request(['vehicles' => 2, 'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ']))->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->workshop->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0, 0, 0, 0], ServiceInventory::where('service_type_id', $this->deluxe->id)->orderBy('service_date')->pluck('booked_units')->all());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }
}
