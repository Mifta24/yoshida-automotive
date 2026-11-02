<?php

namespace Tests\Feature;

use App\Core\Member\Models\Member;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberAdminTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::factory()->withMembers()->create(['slug' => 'demo']);
    }

    private function userWithRole(string $role, ?Workshop $workshop = null): User
    {
        $user = User::factory()->create();
        ($workshop ?? $this->workshop)->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->is_platform_admin = true;
        $user->save();

        return $user;
    }

    public function test_owner_and_front_desk_open_the_member_screens_but_staff_do_not(): void
    {
        $member = Member::factory()->forWorkshop($this->workshop)->create();

        foreach (['owner' => 200, 'front_desk' => 200, 'staff' => 403] as $role => $status) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get(route('admin.members.index'))->assertStatus($status, $role);
            $this->actingAs($user)->get(route('admin.members.show', $member->id))->assertStatus($status, $role);
        }
    }

    public function test_the_list_shows_this_workshops_members_with_their_reservation_counts_and_can_be_searched(): void
    {
        $ayu = Member::factory()->forWorkshop($this->workshop)->create(['name' => 'Ayu Lestari', 'email' => 'ayu@example.test']);
        Member::factory()->forWorkshop($this->workshop)->create(['name' => 'Budi Santoso', 'email' => 'budi@example.test']);
        $elsewhere = Workshop::factory()->withMembers()->create();
        Member::factory()->forWorkshop($elsewhere)->create(['name' => 'Outsider Person', 'email' => 'outsider@example.test']);

        $service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        Booking::create(['reference' => 'BK-AYU001', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'member_id' => $ayu->id, 'customer_name' => 'Ayu', 'vehicle_count' => 1, 'drop_off_date' => now()->addDay()->toDateString(), 'pickup_date' => now()->addDays(2)->toDateString(), 'total_price' => 1]);

        $this->actingAs($this->userWithRole('owner'));

        $this->get(route('admin.members.index'))->assertOk()->assertSee('Ayu Lestari')->assertSee('Budi Santoso')->assertDontSee('Outsider Person');
        $this->get(route('admin.members.index', ['q' => 'budi']))->assertOk()->assertSee('Budi Santoso')->assertDontSee('Ayu Lestari');
    }

    public function test_a_members_page_shows_their_reservations_and_never_another_workshops_member(): void
    {
        $ayu = Member::factory()->forWorkshop($this->workshop)->create(['name' => 'Ayu Lestari']);
        $service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        Booking::create(['reference' => 'BK-AYU001', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'member_id' => $ayu->id, 'customer_name' => 'Ayu', 'vehicle_count' => 1, 'drop_off_date' => now()->addDay()->toDateString(), 'pickup_date' => now()->addDays(2)->toDateString(), 'total_price' => 1]);
        $outsider = Member::factory()->forWorkshop(Workshop::factory()->withMembers()->create())->create();

        $this->actingAs($this->userWithRole('owner'));

        $this->get(route('admin.members.show', $ayu->id))->assertOk()->assertSee('Ayu Lestari')->assertSee('BK-AYU001');
        $this->get(route('admin.members.show', $outsider->id))->assertNotFound();
    }

    public function test_the_team_leaves_internal_notes_on_a_member_that_the_member_never_sees(): void
    {
        $member = Member::factory()->forWorkshop($this->workshop)->create(['email' => 'ayu@example.test']);
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post(route('admin.notes.store', ['type' => 'member', 'id' => $member->id]), ['body' => 'Prefers a quiet service'])->assertRedirect();

        $this->assertDatabaseHas('internal_notes', ['workshop_id' => $this->workshop->id, 'notable_type' => Member::class, 'notable_id' => $member->id, 'user_id' => $owner->id, 'body' => 'Prefers a quiet service']);
        $this->get(route('admin.members.show', $member->id))->assertSee('Prefers a quiet service');

        $this->actingAs($member, 'member')->get('/demo/member')->assertOk()->assertDontSee('Prefers a quiet service');
    }

    public function test_notes_on_members_are_refused_for_staff_and_for_other_workshops_members(): void
    {
        $member = Member::factory()->forWorkshop($this->workshop)->create();
        $outsider = Member::factory()->forWorkshop(Workshop::factory()->withMembers()->create())->create();

        $this->actingAs($this->userWithRole('staff'))->post(route('admin.notes.store', ['type' => 'member', 'id' => $member->id]), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->userWithRole('owner'))->post(route('admin.notes.store', ['type' => 'member', 'id' => $outsider->id]), ['body' => 'x'])->assertNotFound();

        $this->assertDatabaseCount('internal_notes', 0);
    }

    public function test_a_workshop_without_the_add_on_has_no_member_screens_or_menu_entry(): void
    {
        $plain = Workshop::factory()->create();
        $owner = $this->userWithRole('owner', $plain);

        $this->actingAs($owner)->get(route('admin.members.index'))->assertNotFound();
        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee(route('admin.members.index'), false);

        $withAddOn = $this->userWithRole('owner');
        $this->actingAs($withAddOn)->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.members.index'), false);
    }

    public function test_only_the_platform_admin_switches_the_add_on_and_it_is_logged(): void
    {
        $plain = Workshop::factory()->create(['slug' => 'plain']);
        $payload = ['feature' => 'member', 'enabled' => 1];

        $this->patch(route('platform.workshops.features', $plain), $payload)->assertRedirect(route('admin.login'));
        $this->actingAs($this->userWithRole('owner', $plain))->patch(route('platform.workshops.features', $plain), $payload)->assertForbidden();
        $this->assertFalse($plain->fresh()->supportsMembers());

        $admin = $this->platformAdmin();
        $this->actingAs($admin)->patch(route('platform.workshops.features', $plain), $payload)->assertRedirect()->assertSessionHas('status');

        $this->assertTrue($plain->fresh()->supportsMembers());
        $this->assertDatabaseHas('audit_logs', ['workshop_id' => $plain->id, 'action' => 'workshop.feature']);
        $this->get('/plain/member/login')->assertOk();

        $this->actingAs($admin)->patch(route('platform.workshops.features', $plain), ['feature' => 'member', 'enabled' => 0])->assertRedirect();

        $this->assertFalse($plain->fresh()->supportsMembers());
        $this->get('/plain/member/login')->assertNotFound();
    }

    public function test_an_unknown_add_on_is_refused_and_switching_one_keeps_the_others(): void
    {
        $workshop = Workshop::factory()->create(['features' => ['member', 'future-thing']]);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->patch(route('platform.workshops.features', $workshop), ['feature' => 'telepathy', 'enabled' => 1])->assertSessionHasErrors('feature');

        $this->patch(route('platform.workshops.features', $workshop), ['feature' => 'member', 'enabled' => 0])->assertRedirect();
        $this->assertSame(['future-thing'], $workshop->fresh()->features);
    }

    public function test_the_platform_list_shows_each_workshops_add_on_state(): void
    {
        Workshop::factory()->withMembers()->create(['name' => 'Sakura Inn', 'slug' => 'sakura']);

        $this->actingAs($this->platformAdmin())->get(route('platform.workshops.index'))->assertOk()->assertSee('Switch member add-on off for Sakura Inn');
    }
}
