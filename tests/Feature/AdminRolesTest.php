<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\ServiceType;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRolesTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private ServiceType $service;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        $this->booking = Booking::create([
            'reference' => 'BK-ROLE1', 'workshop_id' => $this->workshop->id, 'service_type_id' => $this->service->id, 'customer_name' => 'Ayu', 'vehicle_count' => 1,
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $this->workshop->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    public function test_each_role_only_opens_the_screens_it_is_meant_for(): void
    {
        $matrix = [
            'owner' => ['admin.service-types.index' => 200, 'admin.inventory.index' => 200, 'admin.bookings.index' => 200, 'admin.handovers.index' => 200, 'admin.activity.index' => 200],
            'front_desk' => ['admin.service-types.index' => 403, 'admin.inventory.index' => 200, 'admin.bookings.index' => 200, 'admin.handovers.index' => 200, 'admin.activity.index' => 403],
            'staff' => ['admin.service-types.index' => 403, 'admin.inventory.index' => 403, 'admin.bookings.index' => 200, 'admin.handovers.index' => 200, 'admin.activity.index' => 403],
        ];

        foreach ($matrix as $role => $routes) {
            $user = $this->userWithRole($role);

            foreach ($routes as $route => $status) {
                $this->actingAs($user)->get(route($route))->assertStatus($status, "{$role} on {$route}");
            }

            $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        }
    }

    public function test_an_unknown_role_opens_nothing_but_the_dashboard(): void
    {
        $user = $this->userWithRole('intern');

        $this->actingAs($user)->get(route('admin.bookings.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.handovers.index'))->assertForbidden();
    }

    public function test_staff_can_read_bookings_but_not_change_their_status(): void
    {
        $staff = $this->userWithRole('staff');
        $frontDesk = $this->userWithRole('front_desk');

        $this->actingAs($staff)->patch(route('admin.bookings.status', $this->booking), ['status' => 'confirmed'])->assertForbidden();
        $this->assertSame(Booking::STATUS_PENDING, $this->booking->fresh()->status);

        $this->actingAs($frontDesk)->patch(route('admin.bookings.status', $this->booking), ['status' => 'confirmed'])->assertRedirect();
        $this->assertSame(Booking::STATUS_CONFIRMED, $this->booking->fresh()->status);
    }

    public function test_status_changes_and_notes_are_recorded_on_the_booking_page(): void
    {
        $frontDesk = $this->userWithRole('front_desk');

        $this->actingAs($frontDesk)->patch(route('admin.bookings.status', $this->booking), ['status' => 'confirmed']);
        $this->actingAs($frontDesk)->post(route('admin.notes.store', ['type' => 'booking', 'id' => $this->booking->id]), ['body' => 'Customer wants a quiet floor'])->assertRedirect();

        $this->assertSame(2, AuditLog::where('workshop_id', $this->workshop->id)->count());

        $this->actingAs($frontDesk)->get(route('admin.bookings.show', $this->booking))
            ->assertOk()
            ->assertSee('Customer wants a quiet floor')
            ->assertSee('Changed status from pending to confirmed');
    }

    public function test_notes_cannot_be_added_to_another_workshops_records(): void
    {
        $other = Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $conversation = Conversation::create(['workshop_id' => $other->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $foreign = HandoverRequest::create(['conversation_id' => $conversation->id, 'reason' => 'complaint', 'summary' => 'x']);

        $this->actingAs($this->userWithRole('owner'))
            ->post(route('admin.notes.store', ['type' => 'handover', 'id' => $foreign->id]), ['body' => 'Sneaky'])
            ->assertNotFound();

        $this->assertSame(0, $foreign->internalNotes()->count());
    }

    public function test_the_activity_log_lists_what_the_team_did(): void
    {
        $owner = $this->userWithRole('owner');
        AuditLog::record($this->workshop->id, 'test', 'Opened the vault', null, $owner);

        $this->actingAs($owner)->get(route('admin.activity.index'))->assertOk()->assertSee('Opened the vault')->assertSee($owner->name);
    }
}
