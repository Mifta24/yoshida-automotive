<?php

namespace Tests\Feature;

use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Workshop::create(['name' => 'Yoshida Automotive', 'slug' => 'yoshida', 'public_status' => 'published', 'default_locale' => 'ja', 'currency' => 'JPY']);
    }

    public function test_the_root_goes_straight_to_the_only_workshop(): void
    {
        $this->get('/')->assertRedirect(route('workshop.show', ['workshopSlug' => 'yoshida']));
    }

    public function test_the_home_page_shows_the_hero_concierge_and_five_divisions(): void
    {
        $response = $this->get('/yoshida')->assertOk()->assertSee('愛車のすべてを、')->assertSee('AIコンシェルジュ');

        foreach (['body-paint', 'maintenance', 'insurance', 'sales', 'tires'] as $division) {
            $response->assertSee(route('workshop.division', ['workshopSlug' => 'yoshida', 'division' => $division, 'lang' => 'ja']), false);
        }
    }

    public function test_each_division_panel_opens_and_an_unknown_one_is_missing(): void
    {
        $this->get('/yoshida/divisions/body-paint')->assertOk()->assertSee('板金・塗装')->assertSee('修理の流れ');
        $this->get('/yoshida/divisions/maintenance')->assertOk()->assertSee('車検（継続検査）');
        $this->get('/yoshida/divisions/insurance')->assertOk()->assertSee('対人賠償');
        $this->get('/yoshida/divisions/sales')->assertOk()->assertSee('取り扱い車種');
        $this->get('/yoshida/divisions/tires')->assertOk()->assertSee('https://x-change.tokyo', false);
        $this->get('/yoshida/divisions/spa')->assertNotFound();
    }

    public function test_the_company_page_shows_the_presidents_greeting(): void
    {
        $this->get('/yoshida/company')->assertOk()->assertSee('吉田 直樹')->assertSee('代表者挨拶');
    }

    public function test_the_home_page_offers_the_theme_switch(): void
    {
        $this->get('/yoshida')->assertSee('data-theme-toggle', false)->assertSee('yoshida-theme', false);
    }
}
