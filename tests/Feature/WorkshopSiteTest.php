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

    public function test_the_access_page_shows_contact_details_and_waits_for_an_address(): void
    {
        Workshop::where('slug', 'yoshida')->update(['phone' => '042-556-1219', 'opening_time' => '09:00', 'closing_time' => '18:00']);

        $this->get('/yoshida/access')
            ->assertOk()
            ->assertSee('アクセス')
            ->assertSee('042-556-1219')
            ->assertSee('9:00〜18:00')
            ->assertSee('04-2937-5296')
            ->assertSee('準備中')
            ->assertSee('地図は住所の登録後に表示されます。')
            ->assertDontSee('google.com/maps');
    }

    public function test_the_access_page_links_to_maps_once_the_workshop_has_an_address(): void
    {
        Workshop::where('slug', 'yoshida')->update(['address' => '東京都八王子市1-2-3']);

        $this->get('/yoshida/access')
            ->assertOk()
            ->assertSee('東京都八王子市1-2-3')
            ->assertSee(e('https://www.google.com/maps/search/?api=1&query='.rawurlencode('東京都八王子市1-2-3')), false)
            ->assertDontSee('準備中');
    }

    public function test_the_header_access_link_opens_the_access_page(): void
    {
        $this->get('/yoshida')->assertSee(route('workshop.access', ['workshopSlug' => 'yoshida', 'lang' => 'ja']), false);
    }

    public function test_the_division_company_and_access_pages_follow_the_page_language(): void
    {
        $this->get('/yoshida/divisions/body-paint?lang=en')->assertOk()->assertSee('Body & Paint')->assertSee('Repair process')->assertDontSee('板金・塗装')->assertDontSee('修理の流れ');
        $this->get('/yoshida/divisions/tires?lang=id')->assertOk()->assertSee('Penjualan & Ganti Ban')->assertSee('Layanan kami')->assertDontSee('対応サービス');
        $this->get('/yoshida/divisions/insurance?lang=en')->assertOk()->assertSee('Main coverage')->assertDontSee('主な補償内容');
        $this->get('/yoshida/company?lang=en')->assertOk()->assertSee('Message from the president')->assertSee('President & CEO')->assertDontSee('代表者挨拶');
        $this->get('/yoshida/company?lang=id')->assertOk()->assertSee('Sambutan presiden direktur')->assertDontSee('私たちの想い');
        $this->get('/yoshida/access?lang=id')->assertOk()->assertSee('Informasi toko')->assertSee('Segera hadir')->assertDontSee('店舗情報');
        $this->get('/yoshida/access?lang=ja')->assertOk()->assertSee('店舗情報');
    }

    public function test_the_home_page_offers_the_theme_switch(): void
    {
        $this->get('/yoshida')->assertSee('data-theme-toggle', false)->assertSee('yoshida-theme', false);
    }
}
