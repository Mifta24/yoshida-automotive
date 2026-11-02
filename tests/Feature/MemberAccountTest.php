<?php

namespace Tests\Feature;

use App\Core\Member\Models\Member;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\Workshop;
use App\Services\Advisor\WorkshopAdvisorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberAccountTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    private ServiceType $service;

    private Member $member;

    private CarbonImmutable $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::factory()->withMembers()->create(['slug' => 'demo', 'currency' => 'IDR']);
        $this->service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1000000, 'is_active' => true]);
        $this->member = Member::factory()->forWorkshop($this->workshop)->create(['name' => 'Ayu', 'email' => 'ayu@example.test']);
        $this->start = CarbonImmutable::today()->addDays(10);

        foreach (range(0, 9) as $offset) {
            ServiceInventory::create(['service_type_id' => $this->service->id, 'service_date' => $this->start->addDays($offset)->toDateString(), 'total_units' => 1, 'booked_units' => $offset < 3 ? 1 : 0, 'price' => 1000000]);
        }
    }

    private function booking(string $reference, ?Member $member, array $override = []): Booking
    {
        return Booking::create([
            'reference' => $reference, 'workshop_id' => $this->workshop->id, 'service_type_id' => $this->service->id, 'member_id' => $member?->id, 'customer_name' => 'Ayu',
            'customer_email' => 'ayu@example.test', 'vehicle_count' => 1, 'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'status' => Booking::STATUS_CONFIRMED,
            'drop_off_date' => $this->start->toDateString(), 'pickup_date' => $this->start->addDays(2)->toDateString(), 'total_price' => 2000000, ...$override,
        ]);
    }

    private function booked(int $offset): int
    {
        return ServiceInventory::whereDate('service_date', $this->start->addDays($offset)->toDateString())->value('booked_units');
    }

    private function account(string $suffix = '')
    {
        return '/demo/member'.$suffix;
    }

    public function test_the_account_lists_only_the_members_own_reservations(): void
    {
        $other = Member::factory()->forWorkshop($this->workshop)->create();
        $this->booking('BK-AAAAAA', $this->member);
        $this->booking('BK-BBBBBB', $other);
        $this->booking('BK-CCCCCC', null);

        $this->actingAs($this->member, 'member')->get($this->account())
            ->assertOk()
            ->assertSee('BK-AAAAAA')
            ->assertDontSee('BK-BBBBBB')
            ->assertDontSee('BK-CCCCCC');
    }

    public function test_the_page_follows_the_requested_language_then_the_members_own(): void
    {
        $this->member->update(['locale' => 'ja']);
        $this->actingAs($this->member, 'member');

        $this->get($this->account())->assertSee('ご予約一覧');
        $this->get($this->account('?lang=id'))->assertSee('Reservasi saya');
    }

    public function test_a_new_member_is_asked_for_their_name_then_can_save_their_details(): void
    {
        $member = Member::factory()->forWorkshop($this->workshop)->withoutProfile()->create();

        $this->actingAs($member, 'member')->get($this->account('?lang=en'))->assertSee('Tell us your name');

        $this->put($this->account('/profile'), ['name' => 'Budi', 'phone' => '+62 811 222 333', 'locale' => 'id'])->assertRedirect()->assertSessionHas('status');

        $member->refresh();
        $this->assertSame('Budi', $member->name);
        $this->assertSame('+62 811 222 333', $member->phone);
        $this->assertSame('id', $member->locale);
    }

    public function test_invalid_details_are_refused_and_nothing_changes(): void
    {
        $this->actingAs($this->member, 'member');

        $this->put($this->account('/profile'), ['name' => '', 'phone' => 'call me', 'locale' => 'fr'])->assertSessionHasErrors(['name', 'phone', 'locale']);

        $this->assertSame('Ayu', $this->member->fresh()->name);
    }

    public function test_the_member_moves_their_own_visit_without_typing_a_reference(): void
    {
        $booking = $this->booking('BK-AAAAAA', $this->member);

        $this->actingAs($this->member, 'member')
            ->post($this->account('/bookings/BK-AAAAAA/reschedule'), ['drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString()])
            ->assertRedirect()->assertSessionHas('status');

        $booking->refresh();
        $this->assertSame($this->start->addDays(4)->toDateString(), $booking->drop_off_date->toDateString());
        $this->assertSame(Booking::STATUS_PENDING, $booking->status);
        $this->assertSame(0, $this->booked(0));
        $this->assertSame(1, $this->booked(4));
    }

    public function test_moving_to_unavailable_days_changes_nothing(): void
    {
        $booking = $this->booking('BK-AAAAAA', $this->member, ['drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString()]);

        $this->actingAs($this->member, 'member')
            ->post($this->account('/bookings/BK-AAAAAA/reschedule'), ['drop_off_date' => $this->start->toDateString(), 'pickup_date' => $this->start->addDays(2)->toDateString()])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertSame($this->start->addDays(4)->toDateString(), $booking->fresh()->drop_off_date->toDateString());
    }

    public function test_the_member_cancels_their_own_reservation_and_the_services_are_released(): void
    {
        $booking = $this->booking('BK-AAAAAA', $this->member);

        $this->actingAs($this->member, 'member')->post($this->account('/bookings/bk-aaaaaa/cancel'))->assertRedirect()->assertSessionHas('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
        $this->assertSame(0, $this->booked(0));
    }

    public function test_a_visit_that_has_started_or_was_cancelled_cannot_be_changed(): void
    {
        $started = $this->booking('BK-STARTD', $this->member, ['drop_off_date' => CarbonImmutable::today()->subDay()->toDateString(), 'pickup_date' => CarbonImmutable::today()->addDay()->toDateString()]);
        $cancelled = $this->booking('BK-CANCLD', $this->member, ['status' => Booking::STATUS_CANCELLED]);

        $this->actingAs($this->member, 'member');
        $this->post($this->account('/bookings/BK-STARTD/cancel'))->assertSessionHas('error');
        $this->post($this->account('/bookings/BK-CANCLD/cancel'))->assertSessionHas('error');

        $this->assertSame(Booking::STATUS_CONFIRMED, $started->fresh()->status);
        $this->assertSame(Booking::STATUS_CANCELLED, $cancelled->fresh()->status);
    }

    public function test_a_member_cannot_touch_someone_elses_reservation(): void
    {
        $other = Member::factory()->forWorkshop($this->workshop)->create();
        $theirs = $this->booking('BK-THEIRS', $other);
        $unclaimed = $this->booking('BK-UNCLAM', null);

        $this->actingAs($this->member, 'member');
        $dates = ['drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString()];

        $this->post($this->account('/bookings/BK-THEIRS/cancel'))->assertNotFound();
        $this->post($this->account('/bookings/BK-THEIRS/reschedule'), $dates)->assertNotFound();
        $this->post($this->account('/bookings/BK-UNCLAM/cancel'))->assertNotFound();

        $this->assertSame(Booking::STATUS_CONFIRMED, $theirs->fresh()->status);
        $this->assertSame(Booking::STATUS_CONFIRMED, $unclaimed->fresh()->status);
    }

    public function test_a_reservation_made_while_signed_in_belongs_to_the_member_and_the_form_is_prefilled(): void
    {
        $this->member->update(['phone' => '+62 811 000 111']);
        $this->actingAs($this->member, 'member');

        $this->get('/demo/reservation')->assertOk()->assertSee('value="Ayu"', false)->assertSee('value="+62 811 000 111"', false);

        $this->postJson('/demo/reservation', [
            'service_type_slug' => 'deluxe', 'drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'vehicles' => 1, 'locale' => 'en', 'customer_name' => 'Ayu', 'contact_type' => 'whatsapp', 'contact_value' => '+62 811 000 111',
        ])->assertCreated();

        $this->assertSame($this->member->id, Booking::latest('id')->firstOrFail()->member_id);
    }

    public function test_a_reservation_by_a_customer_who_is_not_signed_in_stays_anonymous(): void
    {
        $this->get('/demo/reservation')->assertOk()->assertDontSee('value="Ayu"', false);

        $this->postJson('/demo/reservation', [
            'service_type_slug' => 'deluxe', 'drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'vehicles' => 1, 'locale' => 'en', 'customer_name' => 'Walk In', 'contact_type' => 'email', 'contact_value' => 'walk@example.test',
        ])->assertCreated();

        $this->assertNull(Booking::latest('id')->firstOrFail()->member_id);
    }

    public function test_signing_out_ends_the_session_on_the_member_pages(): void
    {
        $this->actingAs($this->member, 'member')->post($this->account('/logout'))->assertRedirect();

        $this->assertGuest('member');
        $this->get($this->account())->assertRedirect(route('member.login', ['workshopSlug' => 'demo']));
    }

    public function test_the_workshop_page_offers_member_sign_in_only_when_the_add_on_is_on(): void
    {
        $this->get('/demo/staff')->assertOk()->assertSee('/demo/member', false);

        $plain = Workshop::factory()->create(['slug' => 'plain']);

        $this->get('/plain/staff')->assertOk()->assertDontSee('/plain/member', false);
    }

    public function test_a_booking_the_ai_advisor_makes_for_a_signed_in_member_belongs_to_them(): void
    {
        $conversation = Conversation::create(['workshop_id' => $this->workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'en']);
        $input = [
            'service_type_slug' => 'deluxe', 'drop_off_date' => $this->start->addDays(4)->toDateString(), 'pickup_date' => $this->start->addDays(6)->toDateString(),
            'vehicle_model' => 'Toyota Avanza', 'license_plate' => 'B 1234 XYZ', 'customer_name' => 'Ayu', 'customer_phone' => '+62 811 111 222',
        ];

        (new WorkshopAdvisorTools($this->workshop, $conversation, 'en'))->dispatch('create_booking_request', $input);
        $this->assertNull(Booking::firstOrFail()->member_id);

        $this->actingAs($this->member, 'member');
        (new WorkshopAdvisorTools($this->workshop, $conversation, 'en'))->dispatch('create_booking_request', [...$input, 'drop_off_date' => $this->start->addDays(7)->toDateString(), 'pickup_date' => $this->start->addDays(8)->toDateString()]);

        $this->assertSame([null, $this->member->id], Booking::orderBy('id')->pluck('member_id')->all());
    }

    public function test_the_chat_form_starts_with_the_members_name_and_phone_only_when_signed_in(): void
    {
        $this->member->update(['phone' => '+62 811 000 111']);

        $this->get('/demo')->assertOk()->assertSee('data-member-name=""', false);

        $this->actingAs($this->member, 'member')->get('/demo')->assertOk()
            ->assertSee('data-member-name="Ayu"', false)
            ->assertSee('data-member-phone="+62 811 000 111"', false);
    }
}
