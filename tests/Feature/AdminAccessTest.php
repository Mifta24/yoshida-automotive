<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\ServiceInventory;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private Workshop $otherWorkshop;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $this->otherWorkshop = Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);

        $this->staff = User::factory()->create();
        $this->workshop->users()->attach($this->staff->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function foreignBooking(): Booking
    {
        $service = $this->otherWorkshop->serviceTypes()->create(['name' => 'Foreign', 'slug' => 'foreign', 'base_price' => 1, 'is_active' => true]);

        return Booking::create([
            'reference' => 'BK-FOREIGN', 'workshop_id' => $this->otherWorkshop->id, 'service_type_id' => $service->id, 'customer_name' => 'Foreign Customer',
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
    }

    private function foreignHandover(): HandoverRequest
    {
        $conversation = Conversation::create(['workshop_id' => $this->otherWorkshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);

        return HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Foreign complaint']);
    }

    public function test_visitors_are_sent_to_the_login_page_for_every_admin_screen(): void
    {
        foreach (['admin.dashboard', 'admin.service-types.index', 'admin.knowledge-items.index', 'admin.bookings.index', 'admin.handovers.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }

    public function test_staff_only_see_bookings_of_their_own_workshop(): void
    {
        $this->foreignBooking();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertDontSee('BK-FOREIGN')->assertDontSee('Foreign Customer');
    }

    public function test_staff_cannot_change_another_workshops_booking(): void
    {
        $booking = $this->foreignBooking();

        $this->actingAs($this->staff)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])
            ->assertNotFound();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
    }

    public function test_staff_cannot_read_reply_to_or_resolve_another_workshops_handover(): void
    {
        $handover = $this->foreignHandover();

        $this->actingAs($this->staff)->get(route('admin.handovers.show', $handover))->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'Hello'])->assertNotFound();
        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertNotFound();

        $this->assertSame(0, ConversationMessage::count());
        $this->assertSame(HandoverRequest::STATUS_OPEN, $handover->fresh()->status);
    }

    public function test_staff_reply_reaches_the_customer_and_resolving_returns_the_conversation_to_the_ai(): void
    {
        $conversation = Conversation::create(['workshop_id' => $this->workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en', 'status' => Conversation::STATUS_HANDED_OVER]);
        $handover = HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'Noisy service']);

        $this->actingAs($this->staff)->post(route('admin.handovers.reply', $handover), ['message' => 'We are on it.'])->assertRedirect();
        $this->assertSame('We are on it.', $conversation->messages()->where('role', ConversationMessage::ROLE_STAFF)->value('content'));

        $this->actingAs($this->staff)->post(route('admin.handovers.resolve', $handover))->assertRedirect(route('admin.handovers.index'));
        $this->assertSame(Conversation::STATUS_ACTIVE, $conversation->fresh()->status);
        $this->assertSame(HandoverRequest::STATUS_RESOLVED, $handover->fresh()->status);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertSessionHasErrors();
        }

        $this->post(route('admin.login.store'), ['email' => $this->staff->email, 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_staff_can_give_a_facility_a_photo_and_only_a_real_url_is_accepted(): void
    {
        $item = $this->workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true]);
        $form = ['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => 1];

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'ftp://example.test/pool.jpg'])
            ->assertSessionHasErrors('image_url');

        $this->actingAs($this->staff)
            ->put(route('admin.knowledge-items.update', $item), [...$form, 'image_url' => 'https://example.test/pool.jpg'])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/pool.jpg', $item->fresh()->image_url);
    }

    public function test_a_cancelled_booking_cannot_be_confirmed_again_and_its_services_are_released_once(): void
    {
        $service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        $visit = now()->addDays(3)->toDateString();
        $inventory = ServiceInventory::create(['service_type_id' => $service->id, 'service_date' => $visit, 'total_units' => 2, 'booked_units' => 1, 'price' => 1]);
        $booking = Booking::create([
            'reference' => 'BK-OWN', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'customer_name' => 'Own Customer',
            'drop_off_date' => $visit, 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1, 'vehicle_count' => 1,
        ]);

        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])->assertSessionHasErrors('status');
        $this->actingAs($this->staff)->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CONFIRMED])->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $inventory->fresh()->booked_units);
    }

    public function test_bookings_of_a_deleted_service_type_still_show_and_the_name_can_be_reused(): void
    {
        $service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        Booking::create([
            'reference' => 'BK-OLD', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'customer_name' => 'Old Customer',
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);

        $this->actingAs($this->staff)->delete(route('admin.service-types.destroy', $service))->assertRedirect();

        $this->actingAs($this->staff)->get(route('admin.bookings.index'))->assertOk()->assertSee('Deluxe');
        $this->actingAs($this->staff)->get(route('admin.dashboard'))->assertOk()->assertSee('Old Customer');

        $this->actingAs($this->staff)
            ->post(route('admin.service-types.store'), ['name' => 'Deluxe', 'base_price' => 100, 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $this->workshop->serviceTypes()->count());
    }

    public function test_only_real_web_addresses_are_accepted_for_service_photos(): void
    {
        $form = ['name' => 'Deluxe', 'base_price' => 100, 'is_active' => 1];

        $this->actingAs($this->staff)
            ->post(route('admin.service-types.store'), [...$form, 'new_images' => [['url' => 'javascript:alert(1)']]])
            ->assertSessionHasErrors('new_images.0.url');

        $this->actingAs($this->staff)
            ->post(route('admin.service-types.store'), [...$form, 'new_images' => [['url' => 'https://example.test/service.jpg', 'alt' => 'Service']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('https://example.test/service.jpg', $this->workshop->serviceTypes()->firstOrFail()->images()->firstOrFail()->image_url);
    }
}
