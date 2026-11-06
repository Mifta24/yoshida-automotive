<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Workshop;
use App\Notifications\NewBookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkshopSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['app.url' => 'http://platform.test']);
        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'id']);
        $this->owner = User::factory()->create();
        $this->workshop->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
    }

    private function form(array $override = []): array
    {
        return [
            'name' => 'Demo Resort', 'timezone' => 'Asia/Tokyo', 'currency' => 'jpy', 'default_locale' => 'ja', 'opening_time' => '15:00', 'closing_time' => '11:00',
            'public_status' => 'published', 'phone' => '+81 3 1234 5678', 'whatsapp' => '+81 90 1111 2222', 'email' => 'front@demo.test', ...$override,
        ];
    }

    public function test_the_owner_edits_the_profile_look_and_publishing_of_the_workshop(): void
    {
        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form([
            'logo' => UploadedFile::fake()->image('logo.png', 600, 300),
            'cover' => UploadedFile::fake()->image('cover.jpg', 2400, 1200),
            'ai_instructions' => 'Speak formally.',
            'public_status' => 'draft',
        ]))->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors();

        $workshop = $this->workshop->fresh();
        $this->assertSame('Demo Resort', $workshop->name);
        $this->assertSame('JPY', $workshop->currency);
        $this->assertStringStartsWith('15:00', $workshop->opening_time);
        $this->assertSame('draft', $workshop->public_status);
        $this->assertSame('Speak formally.', $workshop->setting('ai_instructions'));
        Storage::disk('public')->assertExists($workshop->logo_path);
        Storage::disk('public')->assertExists($workshop->cover_path);
        $this->assertDatabaseHas('audit_logs', ['workshop_id' => $workshop->id, 'action' => 'workshop.settings']);

        $oldLogo = $workshop->logo_path;
        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['remove_logo' => 1]))->assertSessionHasNoErrors();
        $this->assertNull($workshop->fresh()->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
    }

    public function test_the_owner_saves_translated_descriptions_without_losing_other_translations(): void
    {
        $this->workshop->update(['description' => 'Bengkel kami', 'translations' => ['ja' => ['name' => '吉田自動車工業', 'description' => 'old']]]);

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form([
            'translations' => [
                'id' => ['description' => ''],
                'en' => ['description' => '  Our workshop  '],
                'ja' => ['description' => '私たちの工場'],
            ],
        ]))->assertSessionHasNoErrors();

        $workshop = $this->workshop->fresh();
        $this->assertSame('Our workshop', $workshop->translatedDescription('en'));
        $this->assertSame('私たちの工場', $workshop->translatedDescription('ja'));
        $this->assertSame('Bengkel kami', $workshop->translatedDescription('id'));
        $this->assertSame('吉田自動車工業', $workshop->translations['ja']['name']);
    }

    public function test_only_owners_open_the_settings_and_the_logo_shows_on_the_workshop_pages(): void
    {
        $frontDesk = User::factory()->create();
        $this->workshop->users()->attach($frontDesk->id, ['role' => 'front_desk', 'status' => 'active']);

        $this->actingAs($frontDesk)->get(route('admin.settings.edit'))->assertForbidden();
        $this->actingAs($frontDesk)->put(route('admin.settings.update'), $this->form())->assertForbidden();

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['logo' => UploadedFile::fake()->image('logo.png', 200, 200), 'cover' => UploadedFile::fake()->image('c.jpg', 800, 400)]));
        $logo = $this->workshop->fresh()->logoUrl();

        $this->get('/demo')->assertOk()->assertSee($logo, false)->assertSee('og:image', false);
    }

    public function test_the_domain_must_look_like_a_domain_and_belong_to_one_workshop_only(): void
    {
        Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published', 'custom_domain' => 'other.example.com']);

        foreach (['https://workshop.example.com', 'workshop', 'workshop.example.com/path', 'other.example.com', 'platform.test'] as $bad) {
            $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => $bad]))->assertSessionHasErrors('custom_domain');
        }

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => 'Visit.Demo.Example.com']))->assertSessionHasNoErrors();
        $this->assertSame('visit.demo.example.com', $this->workshop->fresh()->custom_domain);
    }

    public function test_a_workshops_own_domain_serves_the_workshop_from_its_root_with_slug_free_links(): void
    {
        $this->workshop->update(['custom_domain' => 'visit.demo.example.com']);
        $this->workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'is_active' => true]);
        $base = 'http://visit.demo.example.com';

        $this->get($base.'/')->assertOk()->assertSee($base.'/services', false)->assertDontSee('/demo/services', false);
        $this->get($base.'/services')->assertOk()->assertSee('Deluxe King');
        $this->get($base.'/services/deluxe-king')->assertOk();
        $this->get($base.'/admin/login')->assertOk();

        $this->get('http://unknown.example.com/services')->assertNotFound();
        $this->get('http://platform.test/demo/services')->assertOk();
    }

    public function test_customer_actions_work_on_the_workshops_own_domain(): void
    {
        $this->workshop->update(['custom_domain' => 'visit.demo.example.com']);
        Http::fake();

        $this->postJson('http://visit.demo.example.com/advisor/start', ['locale' => 'en'])->assertOk()->assertJsonStructure(['customer_token']);
    }

    public function test_a_changed_domain_is_picked_up_without_waiting_for_the_cache(): void
    {
        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => 'one.example.com']))->assertSessionHasNoErrors();
        $this->get('http://one.example.com/services')->assertOk();
        $this->assertNotNull(Cache::get('custom-domain:one.example.com'));

        $this->actingAs($this->owner)->put(route('admin.settings.update'), $this->form(['custom_domain' => 'two.example.com']))->assertSessionHasNoErrors();
        $this->get('http://one.example.com/services')->assertNotFound();
        $this->get('http://two.example.com/services')->assertOk();
    }

    public function test_staff_mail_comes_from_the_workshop_name_and_replies_go_to_the_workshop(): void
    {
        $this->workshop->update(['email' => 'front@demo.test']);
        $service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        $booking = Booking::create([
            'reference' => 'BK-MAIL9', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'customer_name' => 'Ayu', 'customer_phone' => '+62 811', 'contact_type' => 'phone', 'vehicle_count' => 1,
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);

        $mail = (new NewBookingRequest($booking))->toMail($this->owner);

        $this->assertSame('Demo', $mail->from[1]);
        $this->assertSame('front@demo.test', $mail->replyTo[0][0]);
    }
}
