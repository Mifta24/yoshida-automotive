<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ServiceInventory;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Advisor\WorkshopAdvisorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_hear_about_new_reservations_and_handovers_but_others_do_not(): void
    {
        Notification::fake();

        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $service = $workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'is_active' => true]);
        $active = User::factory()->create();
        $suspended = User::factory()->create();
        $workshop->users()->attach($active->id, ['role' => 'staff', 'status' => 'active']);
        $workshop->users()->attach($suspended->id, ['role' => 'staff', 'status' => 'suspended']);

        $dropOffDate = CarbonImmutable::today()->addDays(4);
        ServiceInventory::create(['service_type_id' => $service->id, 'service_date' => $dropOffDate->toDateString(), 'total_units' => 2, 'booked_units' => 0, 'price' => 1000000]);

        $this->postJson('/demo/reservation', [
            'service_type_slug' => 'deluxe', 'drop_off_date' => $dropOffDate->toDateString(), 'pickup_date' => $dropOffDate->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'vehicles' => 1, 'customer_name' => 'Ayu', 'contact_type' => 'phone', 'contact_value' => '+62 811 111 222',
        ])->assertCreated();

        Notification::assertSentTo($active, NewBookingRequest::class);
        Notification::assertNotSentTo($suspended, NewBookingRequest::class);

        $conversation = Conversation::create(['workshop_id' => $workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $tools = new WorkshopAdvisorTools($workshop, $conversation, 'en');
        $tools->dispatch('request_human_handover', ['reason' => 'complaint', 'summary' => 'Noisy service']);
        $tools->dispatch('request_human_handover', ['reason' => 'complaint', 'summary' => 'Noisy service, again']);

        Notification::assertSentToTimes($active, NewHandoverRequest::class, 1);
    }

    public function test_the_mail_describes_the_request_and_links_to_the_admin(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $service = $workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        $booking = $workshop->bookings()->create([
            'reference' => 'BK-MAIL1', 'service_type_id' => $service->id, 'customer_name' => 'Ayu', 'customer_phone' => '+62 811', 'contact_type' => 'whatsapp', 'vehicle_count' => 1,
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);

        $mail = (new NewBookingRequest($booking))->toMail(User::factory()->make());

        $this->assertStringContainsString('BK-MAIL1', $mail->subject);
        $this->assertSame(route('admin.bookings.index', ['status' => 'pending']), $mail->actionUrl);
    }
}
