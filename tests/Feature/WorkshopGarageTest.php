<?php

namespace Tests\Feature;

use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkshopGarageTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_screen_lists_every_published_workshop_when_there_are_several(): void
    {
        Workshop::create(['name' => 'First Workshop', 'slug' => 'first', 'public_status' => 'published']);
        Workshop::create(['name' => 'Second Workshop', 'slug' => 'second', 'public_status' => 'published']);
        Workshop::create(['name' => 'Hidden Workshop', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Workshop')->assertSee('Second Workshop')->assertDontSee('Hidden Workshop');
    }

    public function test_opening_screen_explains_when_no_workshop_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The virtual garage is being prepared');
    }

    public function test_chat_has_separate_open_and_close_controls_without_submitting_the_composer(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-chat-launcher', false)
            ->assertSee('data-chat-composer', false)
            ->assertSee('type="button" data-chat-close', false)
            ->assertSee('aria-controls="advisor-chat-log"', false);
    }

    public function test_chat_exposes_consistent_status_draft_and_loading_copy_for_each_locale(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-label-status-sent="Message sent"', false)
            ->assertSee('data-label-status-waiting="Waiting for staff reply"', false)
            ->assertSee('data-label-status-replied="Staff has replied"', false)
            ->assertSee('data-label-draft-title="Unsaved message"', false)
            ->assertSee('data-label-draft-keep="Keep typing"', false)
            ->assertSee('data-label-draft-discard="Discard draft"', false)
            ->assertSee('data-thinking-indicator', false)
            ->assertSee('data-chat-status', false)
            ->assertSee('data-draft-dialog', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-status-sent="メッセージを送信しました"', false)
            ->assertSee('data-label-status-waiting="スタッフの返信を待っています"', false)
            ->assertSee('data-label-status-replied="スタッフが返信しました"', false)
            ->assertSee('data-label-draft-title="未送信のメッセージ"', false)
            ->assertSee('data-label-draft-keep="入力を続ける"', false)
            ->assertSee('data-label-draft-discard="下書きを破棄"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-status-sent="Pesan terkirim"', false)
            ->assertSee('data-label-status-waiting="Menunggu balasan staf"', false)
            ->assertSee('data-label-status-replied="Staf telah membalas"', false)
            ->assertSee('data-label-draft-title="Pesan belum dikirim"', false)
            ->assertSee('data-label-draft-keep="Lanjut mengetik"', false)
            ->assertSee('data-label-draft-discard="Buang draft"', false);
    }

    public function test_chat_composer_offers_a_microphone_button_with_copy_for_each_locale(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('type="button" data-chat-mic', false)
            ->assertSee('data-label-mic="Speak to the advisor"', false)
            ->assertSee('data-label-mic-listening="Listening… tap to stop"', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-mic="コンシェルジュに話しかける"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-mic="Bicara dengan advisor"', false)
            ->assertSee('data-label-mic-denied="Akses mikrofon diblokir. Mohon izinkan di pengaturan browser Anda."', false);
    }

    public function test_chat_header_offers_a_new_chat_button_with_confirmation_copy_for_each_locale(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('type="button" data-chat-new', false)
            ->assertSee('data-reset-dialog', false)
            ->assertSee('data-label-chat-new="New chat"', false)
            ->assertSee('data-label-chat-new-confirm="Start new chat"', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-chat-new="新しいチャット"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-chat-new="Chat baru"', false)
            ->assertSee('data-label-chat-new-keep="Tetap di chat ini"', false);
    }

    public function test_the_facilities_page_lists_only_active_facilities_of_the_current_workshop(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true, 'image_url' => 'https://example.test/pool.jpg']);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);
        $other = Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Other pool', 'body' => 'Other workshop.', 'is_active' => true]);

        $this->get('/demo/facilities')
            ->assertOk()
            ->assertSee('Garden pool')
            ->assertDontSee('Hidden spa')
            ->assertDontSee('Other pool')
            // photo cards replace the menu card, which would cover the right-hand panel
            ->assertSee('class="facility-card"', false)
            ->assertSee('https://example.test/pool.jpg', false)
            ->assertSee('class="garage-content stage-panel-right', false)
            ->assertDontSee('class="garage-navigation"', false);
        $this->get('/demo?lang=en')->assertOk()->assertSee('Everything for your car,');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('愛車のすべてを、');
    }

    public function test_unpublished_garage_is_not_accessible(): void
    {
        Workshop::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }

    public function test_the_services_page_lists_only_active_services_of_the_current_workshop(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $workshop->serviceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000,
            'vehicle_class' => 'suv', 'inclusions' => ['wifi', 'balcony'],
            'warranty_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'King bed', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Bathroom', 'sort_order' => 1]);
        $workshop->serviceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 1800000, 'is_active' => true, 'sort_order' => 1]);
        $workshop->serviceTypes()->create(['name' => 'Retired Service', 'slug' => 'retired-service', 'base_price' => 1, 'is_active' => false]);
        $other = Workshop::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->serviceTypes()->create(['name' => 'Foreign Service', 'slug' => 'foreign-service', 'base_price' => 1, 'is_active' => true]);

        $this->get('/demo/services?lang=en')
            ->assertOk()
            ->assertSee('Deluxe King')
            ->assertSee('Family Suite')
            // the scene itself is the view now: the advisor greets over the
            // backdrop and the index on the right carries the services
            ->assertDontSee('data-service-card', false)
            ->assertSee('narrator-on-stage', false)
            ->assertSee('class="service-nav-thumb"', false)
            ->assertSee('href="'.route('workshop.service', ['workshopSlug' => 'demo', 'serviceSlug' => 'deluxe-king', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Service')
            ->assertDontSee('Foreign Service');
    }

    public function test_a_service_page_shows_its_details_gallery_and_neighbouring_services(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $workshop->serviceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'duration_minutes' => 32,
            'vehicle_class' => 'suv', 'inclusions' => ['wifi', 'balcony'],
            'warranty_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'King bed', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Bathroom', 'sort_order' => 1]);
        $workshop->serviceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 1800000, 'is_active' => true, 'sort_order' => 1]);

        $suiteUrl = route('workshop.service', ['workshopSlug' => 'demo', 'serviceSlug' => 'family-suite', 'lang' => 'en']);

        $this->get('/demo/services/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('Service 1 / 2')
            ->assertSee('SUV')
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and prices are confirmed by workshop staff.')
            // the big photo is marked so stage.js can name it for the transition from the previous scene
            ->assertSee('data-service-photo', false)
            // both neighbours wrap around to the only other service
            ->assertSee('rel="prev"', false)
            ->assertSee($suiteUrl, false);

        $this->get('/demo/services/retired-or-unknown')->assertNotFound();
        $this->get('/demo/services/deluxe-king?lang=id')->assertOk()->assertSee('SUV');
    }

    public function test_services_pages_follow_the_workshop_publication_and_service_state(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $workshop->serviceTypes()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'is_active' => false]);
        Workshop::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/services?lang=en')->assertOk()->assertSee('Service information will be available soon.');
        $this->get('/demo/services/hidden')->assertNotFound();
        $this->get('/draft/services')->assertNotFound();
    }

    public function test_the_garage_walks_the_customer_to_the_services_page(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('workshop.services', ['workshopSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Mari, saya antar ke layanan-layanan kami."', false)
            ->assertSee('href="'.route('workshop.facilities', ['workshopSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Mari, saya antar ke fasilitas bengkel kami."', false)
            ->assertDontSee('data-garage-panel="services"', false)
            ->assertDontSee('data-garage-panel="facilities"', false);

        $this->get('/demo/services?lang=id')
            ->assertOk()
            ->assertSee('data-tour-line="Mari saya antar kembali ke garasi."', false)
            ->assertSee('data-scene="services"', false);
    }

    public function test_reservation_wizard_and_staff_channels_render_from_workshop_data(): void
    {
        $workshop = Workshop::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'whatsapp' => '+62 812-0000-1111',
            'phone' => '+62 361 000', 'email' => 'front@demo.test',
        ]);
        $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('workshop.reservation', ['workshopSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Let me help you plan your visit."', false)
            ->assertSee('href="'.route('workshop.staff', ['workshopSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Let me bring you to our team."', false);

        $this->get('/demo/reservation?lang=en')
            ->assertOk()
            ->assertSee('data-scene="reservation"', false)
            ->assertSee('data-tour-line="Let me walk you back to the garage."', false)
            ->assertSee('data-step="5"', false)
            ->assertSee('value="deluxe-king"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee(route('reservation.store', 'demo'), false);

        $this->get('/demo/reservation?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/reservation?lang=en&service=deluxe-king')
            ->assertOk()
            ->assertSee('data-preselect-service="deluxe-king"', false);

        $this->get('/demo/services/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('href="'.e(route('workshop.reservation', ['workshopSlug' => 'demo', 'lang' => 'en', 'service' => 'deluxe-king'])).'"', false);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('data-scene="staff"', false)
            ->assertSee('data-tour-line="Let me walk you back to the garage."', false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:front@demo.test', false);

        $this->get('/draft/staff')->assertNotFound();
        $this->get('/draft/reservation')->assertNotFound();
    }

    public function test_staff_scene_docks_chat_away_from_the_contact_panel(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('data-chat-dock="left"', false)
            ->assertSee('class="garage-content staff-panel', false);
    }

    public function test_information_scene_centers_the_chat_dock(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-chat-dock="center"', false)
            ->assertSee('class="garage-content info-panel', false);
    }

    public function test_every_stage_scene_uses_the_same_chat_controls_and_dock_contract(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'is_active' => true]);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Open all day.', 'is_active' => true]);

        foreach (['/demo', '/demo/info', '/demo/services', '/demo/facilities', '/demo/reservation', '/demo/staff'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee('data-chat-dock="', false)
                ->assertSee('data-chat-launcher', false)
                ->assertSee('data-chat-composer', false)
                ->assertSee('aria-controls="advisor-chat-log"', false);
        }
    }

    public function test_each_facility_has_its_own_page_with_neighbours_and_an_index(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $pool = $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open 07:00 to 20:00.', 'is_active' => true, 'sort_order' => 0]);
        $gym = $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);

        $url = fn ($item) => route('workshop.facility', ['workshopSlug' => 'demo', 'facilityId' => $item->id, 'lang' => 'en']);

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('data-scene="facilities"', false)
            ->assertSee($url($pool), false)
            ->assertSee($url($gym), false)
            ->assertDontSee($url($hidden), false);

        $this->get($url($pool))
            ->assertOk()
            ->assertSee('Facility 1 / 2')
            ->assertSee('Open 07:00 to 20:00.')
            ->assertSee('Ask about this facility')
            // both neighbours wrap around to the only other facility
            ->assertSee('rel="prev"', false)
            ->assertSee($url($gym), false);

        $this->get($url($hidden))->assertNotFound();
        $this->get('/demo/facilities/999999')->assertNotFound();
        $this->get('/draft/facilities')->assertNotFound();
        $this->get('/draft/info')->assertNotFound();
    }

    public function test_the_workshop_information_scene_lists_only_approved_about_policy_and_faq_entries(): void
    {
        $workshop = Workshop::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'address' => 'Jl. Pantai 8', 'city' => 'Bali', 'country' => 'Indonesia',
            'opening_time' => '14:00', 'closing_time' => '12:00', 'description' => 'A beachfront resort.', 'latitude' => -8.8, 'longitude' => 115.23,
        ]);
        $workshop->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free until 48 hours before arrival.', 'is_active' => true]);
        $workshop->knowledgeItems()->create(['category' => 'faq', 'title' => 'Is parking free?', 'body' => 'Yes, for customers.', 'is_active' => true]);
        $workshop->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Belongs to facilities.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('workshop.info', ['workshopSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Let me tell you more about the workshop."', false)
            ->assertDontSee('data-garage-panel="info"', false)
            ->assertDontSee('Free until 48 hours before arrival.');

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('data-tour-line="Let me walk you back to the garage."', false)
            ->assertSee('Free until 48 hours before arrival.')
            ->assertSee('Is parking free?')
            ->assertSee('class="info-visit-time"', false)
            ->assertSeeInOrder(['14:00', '12:00'])
            ->assertSee('https://www.google.com/maps?q=-8.8000000,115.2300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_garage_shows_a_localised_loader_and_the_opening_screen_too(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=id')->assertOk()->assertSee('data-stage-loader', false)->assertSee('Menyiapkan pengalaman bengkel Anda');
        Workshop::create(['name' => 'Second', 'slug' => 'second', 'public_status' => 'published']);
        $this->get('/?lang=en')->assertOk()->assertSee('data-stage-exit', false)->assertSee('Preparing your workshop experience');
    }

    public function test_the_advisor_introduces_services_using_only_stored_workshop_data(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $workshop->serviceTypes()->create([
            'name' => 'Deluxe King', 'slug' => 'deluxe-king', 'description' => 'A calm service with a garden outlook', 'base_price' => 950000, 'duration_minutes' => 32, 'vehicle_class' => 'suv', 'warranty_included' => true, 'is_active' => true, 'sort_order' => 0,
        ]);
        $workshop->serviceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2200000, 'warranty_included' => false, 'is_active' => true, 'sort_order' => 1]);

        $this->get('/demo/services?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('We have 2 service types, from IDR 950.000.');

        $this->get('/demo/services/deluxe-king?lang=en')
            ->assertOk()
            ->assertSee('Deluxe King. A calm service with a garden outlook. It takes about 32 minutes. SUV. Workmanship warranty is included. Prices start from IDR 950.000.');

        $this->get('/demo/services/family-suite?lang=en')
            ->assertOk()
            ->assertSee('Family Suite. Prices start from IDR 2.200.000. Final availability and prices are confirmed by our team.');

        $this->get('/demo/services?lang=id')->assertOk()->assertSee('Ada 2 tipe layanan, mulai dari IDR 950.000.');
    }

    public function test_no_services_narration_is_shown_when_the_workshop_has_no_services(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/services?lang=en')->assertOk()->assertDontSee('data-key="services"', false)->assertDontSee('Here are our services');
    }

    public function test_the_advisor_introduces_facilities_and_workshop_information_from_stored_data(): void
    {
        $workshop = Workshop::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia',
            'opening_time' => '14:00', 'closing_time' => '12:00',
        ]);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => "Open 07:00 to 20:00.\nTowels included.", 'is_active' => true, 'sort_order' => 0]);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $workshop->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $pool = $workshop->knowledgeItems()->where('title', 'Garden pool')->firstOrFail();

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('We have 2 facilities and services, including Garden pool; Gym.')
            ->assertDontSee('including Garden pool; Gym; Hidden spa');

        $this->get(route('workshop.facility', ['workshopSlug' => 'demo', 'facilityId' => $pool->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-key="facility-', false)
            ->assertSee('data-text="Open 07:00 to 20:00.', false);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Demo. We are located in Bali, Indonesia. We are open from 14:00 to 12:00.');

        $this->get('/demo/info?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Kami berada di Bali, Indonesia.');
    }

    public function test_workshop_information_narration_skips_missing_data(): void
    {
        Workshop::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. We are open from 09:00 to 18:00. Below you will find the address')
            ->assertDontSee('We are located in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_garage_in_every_language(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        Workshop::create(['name' => 'Second', 'slug' => 'second', 'public_status' => 'published']);

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo/info?lang=ja')
            ->assertOk()
            ->assertSee('サウンドオン')
            ->assertSee('data-lang="ja-JP"', false)
            ->assertSee('data-voice-preference="female"', false);
    }

    public function test_the_services_scenes_swap_the_menu_for_a_service_index(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $workshop->serviceTypes()->create(['name' => 'Deluxe King', 'slug' => 'deluxe-king', 'base_price' => 950000, 'is_active' => true, 'sort_order' => 0]);
        $workshop->serviceTypes()->create(['name' => 'Family Suite', 'slug' => 'family-suite', 'base_price' => 2200000, 'is_active' => true, 'sort_order' => 1]);
        $workshop->serviceTypes()->create(['name' => 'Retired Service', 'slug' => 'retired', 'base_price' => 1, 'is_active' => false]);

        $this->get('/demo/services/family-suite?lang=id')
            ->assertOk()
            ->assertSee('class="service-nav"', false)
            ->assertSee('mulai dari IDR 950.000')
            ->assertDontSee('Retired Service')
            // the way back to the main menu stays in the card
            ->assertSee('class="stage-menu-back"', false)
            ->assertSee('href="'.route('workshop.show', ['workshopSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertDontSee('data-garage-link="services"', false);

        // the open service is marked as current in the index, the others are not
        $index = $this->get('/demo/services/family-suite?lang=id')->getContent();
        $navigation = Str::between($index, '<nav class="service-nav">', '</nav>');
        $this->assertStringContainsString('aria-current="page"', Str::after($navigation, 'serviceSlug=family-suite') ?: $navigation);
        $this->assertSame(1, substr_count($navigation, 'aria-current'));

        // the garage keeps the ordinary menu
        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('workshop.reservation', ['workshopSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertDontSee('class="service-nav"', false);
    }

    public function test_stage_scenes_layer_the_cut_out_advisor_over_the_drawn_backdrop(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/services')
            ->assertOk()
            ->assertSee('class="stage-character"', false)
            ->assertSee('images/workshop-backdrop.svg', false)
            ->assertSee('character greeting.webp', false);
    }

    public function test_facility_icons_follow_what_the_entry_is_about(): void
    {
        $workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $icon = fn (string $title, array $tags = []) => $workshop->knowledgeItems()->make(['category' => 'facilities', 'title' => $title, 'body' => '-', 'tags' => $tags])->iconName();

        $this->assertSame('lounge', $icon('Ruang tunggu'));
        $this->assertSame('bay', $icon('Service bay'));
        $this->assertSame('parking', $icon('Parkir dan Wi-Fi'));
        $this->assertSame('price_list', $icon('Daftar harga'));
        $this->assertSame('transport', $icon('Layanan derek'));
        $this->assertSame('place', $icon('Bengkel terdekat'));
        $this->assertSame('wifi', $icon('Internet', ['wifi']));
        $this->assertSame('star', $icon('Kids club'));
    }

    public function test_every_menu_item_and_chat_chip_leads_to_its_scene_with_a_topic_for_the_advisor(): void
    {
        Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $scenes = [
            'workshop.services' => 'Saya ingin melihat pilihan layanan yang tersedia.',
            'workshop.facilities' => 'Apa saja fasilitas yang tersedia di workshop ini?',
            'workshop.info' => 'Apa kebijakan drop-off, pickup, dan pembatalan?',
            'workshop.reservation' => 'Saya ingin reservasi layanan. Bantu saya cek ketersediaan.',
            'workshop.staff' => 'Saya ingin bicara dengan staf bengkel.',
        ];

        $response = $this->get('/demo?lang=id')->assertOk();

        foreach ($scenes as $route => $topic) {
            $url = route($route, ['workshopSlug' => 'demo', 'lang' => 'id']);

            $response->assertSee('href="'.$url.'"', false);
            $this->get($url)->assertOk();
        }

        $response->assertSee('data-topic="Saya ingin melihat pilihan layanan yang tersedia."', false)
            ->assertSeeInOrder(['class="chat-chips"', 'data-stage-exit', 'data-topic="Saya ingin melihat pilihan layanan yang tersedia."'], false)
            ->assertSee('data-topic="Saya ingin bicara dengan staf bengkel."', false);
    }
}
