<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformWorkshopTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        $user = User::factory()->create();
        $user->is_platform_admin = true;
        $user->save();

        return $user;
    }

    private function form(array $override = []): array
    {
        return ['name' => 'Sakura Inn', 'currency' => 'jpy', 'default_locale' => 'ja', 'owner_email' => 'owner@sakura.test', 'owner_name' => 'Hana', ...$override];
    }

    public function test_only_platform_admins_open_the_platform_screens(): void
    {
        $this->get(route('platform.workshops.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('platform.workshops.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('platform.workshops.store'), $this->form())->assertForbidden();
        $this->actingAs($this->platformAdmin())->get(route('platform.workshops.index'))->assertOk();
    }

    public function test_a_new_workshop_starts_hidden_with_its_owner_and_is_published_later(): void
    {
        $this->actingAs($this->platformAdmin())->post(route('platform.workshops.store'), $this->form())->assertRedirect()->assertSessionHas('status');

        $workshop = Workshop::where('slug', 'sakura-inn')->firstOrFail();
        $owner = User::where('email', 'owner@sakura.test')->firstOrFail();

        $this->assertSame('draft', $workshop->public_status);
        $this->assertSame('JPY', $workshop->currency);
        $this->assertSame('owner', $workshop->users()->firstOrFail()->pivot->role);
        $this->assertSame($workshop->id, $owner->currentWorkshop()->id);
        $this->assertDatabaseHas('audit_logs', ['workshop_id' => $workshop->id, 'action' => 'workshop.created']);

        $this->get('/sakura-inn')->assertNotFound();

        $this->actingAs($this->platformAdmin())->patch(route('platform.workshops.status', $workshop), ['public_status' => 'published'])->assertRedirect();
        $this->get('/sakura-inn')->assertOk();
    }

    public function test_reserved_and_taken_addresses_are_refused(): void
    {
        $admin = $this->platformAdmin();
        Workshop::create(['name' => 'Taken', 'slug' => 'taken', 'public_status' => 'published']);

        $this->actingAs($admin)->post(route('platform.workshops.store'), $this->form(['slug' => 'admin']))->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('platform.workshops.store'), $this->form(['slug' => 'taken']))->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post(route('platform.workshops.store'), $this->form(['slug' => 'Bad Slug!']))->assertSessionHasErrors('slug');
    }

    public function test_an_existing_account_becomes_owner_without_a_new_password(): void
    {
        $existing = User::factory()->create(['email' => 'owner@sakura.test']);
        $before = $existing->password;

        $this->artisan('workshop:create', ['name' => 'Sakura Inn', '--owner-email' => 'owner@sakura.test', '--city' => 'Kyoto'])->assertSuccessful();

        $this->assertSame($before, $existing->fresh()->password);
        $this->assertSame('owner', Workshop::firstOrFail()->users()->firstOrFail()->pivot->role);
        $this->assertSame(1, User::count());
    }

    public function test_the_same_name_gets_its_own_address(): void
    {
        $this->artisan('workshop:create', ['name' => 'Sakura Inn', '--owner-email' => 'a@x.test'])->assertSuccessful();
        $this->artisan('workshop:create', ['name' => 'Sakura Inn', '--owner-email' => 'b@x.test'])->assertSuccessful();

        $this->assertEqualsCanonicalizing(['sakura-inn', 'sakura-inn-2'], Workshop::pluck('slug')->all());
    }

    public function test_someone_in_two_workshops_can_switch_between_them_but_not_into_a_third(): void
    {
        $first = Workshop::create(['name' => 'First', 'slug' => 'first', 'public_status' => 'published']);
        $second = Workshop::create(['name' => 'Second', 'slug' => 'second', 'public_status' => 'published']);
        $third = Workshop::create(['name' => 'Third', 'slug' => 'third', 'public_status' => 'published']);
        $user = User::factory()->create();
        $first->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);
        $second->users()->attach($user->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertSee('Switch workshop', false);

        $this->actingAs($user)->post(route('admin.switch-workshop'), ['workshop_id' => $second->id])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($second->id, $user->currentWorkshop()->id);
        $this->assertSame('staff', $user->currentRole());

        $this->actingAs($user)->post(route('admin.switch-workshop'), ['workshop_id' => $third->id])->assertForbidden();
    }

    public function test_the_platform_command_grants_and_revokes_access(): void
    {
        $user = User::factory()->create();

        $this->artisan('platform:admin', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_platform_admin);

        $this->artisan('platform:admin', ['email' => $user->email, '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->is_platform_admin);
    }
}
