<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\CustomerChangedBooking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerBookingTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private ServiceType $service;

    private Booking $booking;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'is_active' => true]);
        $this->start = CarbonImmutable::today()->addDays(10);

        foreach (range(0, 9) as $offset) {
            ServiceInventory::create(['service_type_id' => $this->service->id, 'service_date' => $this->start->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => $offset < 3 ? 1 : 0, 'price' => 1000000 + $offset * 100000]);
        }

        $this->booking = Booking::create([
            'reference' => 'BK-CUSTOMER1', 'workshop_id' => $this->workshop->id, 'service_type_id' => $this->service->id, 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 1112 223',
            'contact_type' => 'phone', 'vehicle_count' => 1, 'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'status' => Booking::STATUS_CONFIRMED,
            'drop_off_date' => $this->start->toDateString(), 'pickup_date' => $this->start->addDays(2)->toDateString(), 'total_price' => 2100000,
        ]);

        $staff = User::factory()->create();
        $this->workshop->users()->attach($staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function credentials(array $override = []): array
    {
        return ['reference' => 'bk-customer1', 'contact' => '0811 1112 223', ...$override];
    }

    private function booked(int $offset): int
    {
        return ServiceInventory::whereDate('service_date', $this->start->addDays($offset)->toDateString())->value('booked_units');
    }

    public function test_a_customer_finds_their_booking_with_the_reference_and_matching_contact_only(): void
    {
        $this->postJson('/demo/booking/lookup', $this->credentials())->assertOk()->assertJsonPath('reference', 'BK-CUSTOMER1')->assertJsonPath('changeable', true);
        $this->postJson('/demo/booking/lookup', $this->credentials(['contact' => '0899 000 111']))->assertNotFound();
        $this->postJson('/demo/booking/lookup', $this->credentials(['reference' => 'BK-NOPE00']))->assertNotFound();

        $this->booking->update(['customer_phone' => null, 'customer_email' => 'Ayu@Example.test']);
        $this->postJson('/demo/booking/lookup', $this->credentials(['contact' => 'ayu@example.test']))->assertOk();
    }

    public function test_a_booking_of_another_workshop_cannot_be_found_through_this_one(): void
    {
        Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->postJson('/other/booking/lookup', $this->credentials())->assertNotFound();
    }

    public function test_rescheduling_moves_the_services_reprices_the_visit_and_asks_the_workshop_to_confirm_again(): void
    {
        Notification::fake();

        $this->postJson('/demo/booking/reschedule', $this->credentials(['drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString()]))
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('total', 1400000);

        $this->assertSame(0, $this->booked(0));
        $this->assertSame(0, $this->booked(1));
        $this->assertSame(0, $this->booked(2));
        $this->assertSame(1, $this->booked(4));
        $this->assertSame(1, $this->booked(5));
        $this->assertSame(Booking::STATUS_PENDING, $this->booking->fresh()->status);
        Notification::assertSentTo($this->workshop->users()->first(), CustomerChangedBooking::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.rescheduled', 'actor' => 'customer']);
    }

    public function test_unavailable_days_leave_the_booking_exactly_as_it_was(): void
    {
        ServiceInventory::whereDate('service_date', $this->start->addDays(5)->toDateString())->update(['booked_units' => 1]);

        $this->postJson('/demo/booking/reschedule', $this->credentials(['drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString()]))
            ->assertUnprocessable();

        $fresh = $this->booking->fresh();
        $this->assertSame($this->start->toDateString(), $fresh->drop_off_date->toDateString());
        $this->assertSame(Booking::STATUS_CONFIRMED, $fresh->status);
        $this->assertSame(1, $this->booked(0));
        $this->assertSame(1, $this->booked(1));
        $this->assertSame(0, $this->booked(4));
    }

    public function test_a_visit_can_be_moved_to_a_single_day(): void
    {
        $day = $this->start->addDays(4)->toDateString();

        $this->postJson('/demo/booking/reschedule', $this->credentials(['drop_off_date' => $day, 'pickup_date' => $day]))->assertOk();

        $this->assertSame(1, $this->booked(4));
        $this->assertSame(0, $this->booked(5));
    }

    public function test_a_visit_can_overlap_its_own_days_when_moved_by_one_day(): void
    {
        $this->postJson('/demo/booking/reschedule', $this->credentials(['drop_off_date' => $this->start->addDay()->toDateString(), 'pickup_date' => $this->start->addDays(3)->toDateString()]))->assertOk();

        $this->assertSame(0, $this->booked(0));
        $this->assertSame(1, $this->booked(1));
        $this->assertSame(1, $this->booked(2));
    }

    public function test_cancelling_releases_the_services_once_and_a_cancelled_booking_cannot_be_changed(): void
    {
        Notification::fake();

        $this->postJson('/demo/booking/cancel', $this->credentials())->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertSame(0, $this->booked(0));

        $this->postJson('/demo/booking/cancel', $this->credentials())->assertStatus(409);
        $this->postJson('/demo/booking/reschedule', $this->credentials(['drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(5)->toDateString()]))->assertStatus(409);
        $this->assertSame(0, $this->booked(4));
        Notification::assertSentTimes(CustomerChangedBooking::class, 1);
    }

    public function test_a_visit_that_has_already_started_cannot_be_changed_online(): void
    {
        $this->booking->update(['drop_off_date' => CarbonImmutable::yesterday()->toDateString(), 'pickup_date' => CarbonImmutable::tomorrow()->toDateString()]);

        $this->postJson('/demo/booking/cancel', $this->credentials())->assertStatus(409);
        $this->postJson('/demo/booking/lookup', $this->credentials())->assertOk()->assertJsonPath('changeable', false);
    }

    public function test_the_management_page_opens_in_the_customers_language(): void
    {
        $this->get('/demo/booking?lang=ja')->assertOk()->assertSee('ご予約の確認・変更');
        $this->get('/demo/booking?lang=id')->assertOk()->assertSee('Kelola reservasi saya');
    }
}
