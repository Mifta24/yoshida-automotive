<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\ServiceInventory;
use App\Models\Workshop;
use App\Services\Advisor\WorkshopAdvisorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvisorBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_requested_services(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $service = $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'is_active' => true]);
        $conversation = Conversation::create(['workshop_id' => $workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        $dropOffDate = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1, 2] as $offset) {
            ServiceInventory::create(['service_type_id' => $service->id, 'service_date' => $dropOffDate->addDays($offset)->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);
        }

        $tools = new WorkshopAdvisorTools($workshop, $conversation, 'en');
        $input = [
            'service_type_slug' => 'deluxe-king', 'drop_off_date' => $dropOffDate->toDateString(), 'pickup_date' => $dropOffDate->addDays(2)->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 111 222',
        ];

        $quote = $tools->dispatch('check_availability', [...$input, 'towing' => false]);
        $this->assertSame(1000000.0, $quote['ui']['quote']['grand_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'vehicles' => 2]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(2, $booking->vehicle_count);
        $this->assertSame(2000000.0, (float) $booking->total_price);
        $this->assertSame([2, 2, 2], ServiceInventory::orderBy('service_date')->pluck('booked_units')->all());

        $tooMany = $tools->dispatch('create_booking_request', [...$input, 'vehicles' => 2]);
        $this->assertNull($tooMany['ui']);
        $this->assertSame(1, Booking::count());
    }

    public function test_ai_tools_cannot_book_a_service_type_the_workshop_has_hidden(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $service = $workshop->serviceTypes()->create(['name' => 'Hidden Suite', 'slug' => 'hidden-suite', 'base_price' => 1000000, 'is_active' => false]);
        $conversation = Conversation::create(['workshop_id' => $workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        $dropOffDate = CarbonImmutable::now()->addDays(5);
        ServiceInventory::create(['service_type_id' => $service->id, 'service_date' => $dropOffDate->toDateString(), 'total_units' => 3, 'booked_units' => 0, 'price' => 1000000]);

        $result = (new WorkshopAdvisorTools($workshop, $conversation, 'en'))->dispatch('create_booking_request', [
            'service_type_slug' => 'hidden-suite', 'drop_off_date' => $dropOffDate->toDateString(), 'pickup_date' => $dropOffDate->addDay()->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 111 222',
        ]);

        $this->assertNull($result['ui']);
        $this->assertSame(0, Booking::count());
    }

    public function test_a_tool_call_with_bad_arguments_is_reported_back_instead_of_failing_the_turn(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'is_active' => true]);
        $conversation = Conversation::create(['workshop_id' => $workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $tools = new WorkshopAdvisorTools($workshop, $conversation, 'en');

        foreach ([
            ['check_availability', ['service_type_slug' => 'deluxe-king', 'drop_off_date' => 'besok', 'pickup_date' => 'lusa']],
            ['search_services', ['vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ']],
            ['request_human_handover', ['reason' => 'complaint']],
        ] as [$tool, $input]) {
            $result = $tools->dispatch($tool, $input);

            $this->assertNull($result['ui']);
            $this->assertStringContainsString('rejected', $result['text']);
        }
    }

    public function test_a_second_handover_call_updates_the_open_request_instead_of_duplicating_it(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $conversation = Conversation::create(['workshop_id' => $workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $tools = new WorkshopAdvisorTools($workshop, $conversation, 'en');

        $tools->dispatch('request_human_handover', ['reason' => 'complaint', 'summary' => 'Noisy service']);
        $tools->dispatch('request_human_handover', ['reason' => 'complaint', 'summary' => 'Noisy service and broken AC']);

        $this->assertSame(1, HandoverRequest::count());
        $this->assertSame('Noisy service and broken AC', HandoverRequest::firstOrFail()->summary);
    }

    public function test_availability_and_booking_agree_on_the_number_of_services_and_long_text_is_trimmed(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $service = $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'is_active' => true]);
        $conversation = Conversation::create(['workshop_id' => $workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $dropOffDate = CarbonImmutable::now()->addDays(5);
        ServiceInventory::create(['service_type_id' => $service->id, 'service_date' => $dropOffDate->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 1000000]);
        $tools = new WorkshopAdvisorTools($workshop, $conversation, 'en');
        $visit = ['service_type_slug' => 'deluxe-king', 'drop_off_date' => $dropOffDate->toDateString()];

        $this->assertTrue($tools->dispatch('check_availability', [...$visit, 'vehicles' => 2])['ui']['available']);
        $this->assertFalse($tools->dispatch('check_availability', [...$visit, 'vehicles' => 3])['ui']['available']);

        $tools->dispatch('create_booking_request', [...$visit, 'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => str_repeat('A', 300), 'customer_phone' => '+62 811 111 222', 'notes' => str_repeat('n', 900)]);

        $booking = Booking::firstOrFail();
        $this->assertSame(100, mb_strlen($booking->customer_name));
        $this->assertSame(500, mb_strlen($booking->notes));
    }

    public function test_unconfirmed_requests_expire_and_release_their_services(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $service = $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 1000000, 'is_active' => true]);
        $visit = CarbonImmutable::now()->addDays(5);
        $inventory = ServiceInventory::create(['service_type_id' => $service->id, 'service_date' => $visit->toDateString(), 'total_units' => 3, 'booked_units' => 2, 'price' => 1000000]);

        $make = fn (string $reference, string $status, int $hoursOld) => tap(Booking::create([
            'reference' => $reference, 'workshop_id' => $workshop->id, 'service_type_id' => $service->id, 'customer_name' => 'Customer', 'vehicle_count' => 1,
            'drop_off_date' => $visit->toDateString(), 'pickup_date' => $visit->addDay()->toDateString(), 'total_price' => 1, 'status' => $status,
        ]), fn (Booking $booking) => $booking->forceFill(['created_at' => now()->subHours($hoursOld)])->save());

        $old = $make('BK-OLD', Booking::STATUS_PENDING, 30);
        $fresh = $make('BK-NEW', Booking::STATUS_PENDING, 2);
        $confirmed = $make('BK-OK', Booking::STATUS_CONFIRMED, 90);

        $this->artisan('bookings:expire-pending')->assertSuccessful();

        $this->assertSame(Booking::STATUS_CANCELLED, $old->fresh()->status);
        $this->assertSame(Booking::STATUS_PENDING, $fresh->fresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $confirmed->fresh()->status);
        $this->assertSame(1, $inventory->fresh()->booked_units);
    }
}
