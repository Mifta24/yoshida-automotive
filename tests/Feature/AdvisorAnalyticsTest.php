<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\HandoverRequest;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvisorAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workshop = Workshop::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
    }

    private function chat(string $locale, array $customerTexts, array $toolCalls = []): Conversation
    {
        $conversation = Conversation::create(['workshop_id' => $this->workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => $locale]);

        foreach ($customerTexts as $text) {
            $conversation->messages()->create(['role' => ConversationMessage::ROLE_CUSTOMER, 'content' => $text]);
            $conversation->messages()->create(['role' => ConversationMessage::ROLE_ASSISTANT, 'content' => 'Answer', 'tool_calls' => $toolCalls ?: null]);
        }

        return $conversation;
    }

    public function test_the_report_counts_chats_languages_gaps_conversion_and_ratings(): void
    {
        $service = $this->workshop->serviceTypes()->create(['name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 1, 'is_active' => true]);
        $booked = $this->chat('en', ['Is there a pool?', 'Is there a pool?!']);
        $this->chat('id', ['Ada gym?'], [['name' => 'search_knowledge', 'input' => ['query' => 'Gym'], 'miss' => true]]);
        $this->chat('id', ['Ada Gym?'], [['name' => 'search_knowledge', 'input' => ['query' => 'gym'], 'miss' => true]]);
        Conversation::create(['workshop_id' => $this->workshop->id, 'customer_token' => (string) Str::uuid(), 'locale' => 'ja']);

        Booking::create([
            'reference' => 'BK-AN1', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'conversation_id' => $booked->id, 'customer_name' => 'Ayu', 'vehicle_count' => 1,
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
        Booking::create([
            'reference' => 'BK-AN2', 'workshop_id' => $this->workshop->id, 'service_type_id' => $service->id, 'customer_name' => 'Walk-in', 'vehicle_count' => 1,
            'drop_off_date' => now()->addDays(3)->toDateString(), 'pickup_date' => now()->addDays(4)->toDateString(), 'total_price' => 1,
        ]);
        HandoverRequest::create(['conversation_id' => $booked->id, 'reason' => 'low_confidence', 'summary' => 'Asked about helipad']);
        $booked->messages()->where('role', 'assistant')->first()->update(['rating' => -1]);

        $owner = User::factory()->create();
        $this->workshop->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Chats started')
            ->assertSee('3 with a customer message')
            ->assertSee('33.3%')
            ->assertSee('1 of 2 bookings came from chat')
            ->assertSee('Indonesian')
            ->assertSee('is there a pool')
            ->assertSee('gym')
            ->assertSee('2×')
            ->assertSee('Asked about helipad');
    }

    public function test_a_customer_can_rate_and_unrate_a_reply_of_their_own_chat_only(): void
    {
        $chat = $this->chat('en', ['Hello']);
        $reply = $chat->messages()->where('role', 'assistant')->firstOrFail();
        $other = $this->chat('en', ['Hi']);

        $this->postJson('/demo/advisor/feedback', ['customer_token' => $chat->customer_token, 'message_id' => $reply->id, 'rating' => 1])->assertOk()->assertJsonPath('rating', 1);
        $this->assertSame(1, $reply->fresh()->rating);

        $this->postJson('/demo/advisor/feedback', ['customer_token' => $chat->customer_token, 'message_id' => $reply->id, 'rating' => 0])->assertOk();
        $this->assertNull($reply->fresh()->rating);

        $this->postJson('/demo/advisor/feedback', ['customer_token' => $other->customer_token, 'message_id' => $reply->id, 'rating' => -1])->assertNotFound();
        $this->postJson('/demo/advisor/feedback', ['customer_token' => $chat->customer_token, 'message_id' => $reply->id, 'rating' => 5])->assertUnprocessable();

        $customerMessage = $chat->messages()->where('role', 'customer')->firstOrFail();
        $this->postJson('/demo/advisor/feedback', ['customer_token' => $chat->customer_token, 'message_id' => $customerMessage->id, 'rating' => 1])->assertNotFound();

        $this->getJson('/demo/advisor/history?customer_token='.$chat->customer_token)->assertJsonPath('messages.1.id', $reply->id);
    }

    public function test_front_desk_sees_analytics_but_plain_staff_do_not(): void
    {
        $frontDesk = User::factory()->create();
        $staff = User::factory()->create();
        $this->workshop->users()->attach($frontDesk->id, ['role' => 'front_desk', 'status' => 'active']);
        $this->workshop->users()->attach($staff->id, ['role' => 'staff', 'status' => 'active']);

        $this->actingAs($frontDesk)->get(route('admin.analytics.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.analytics.index'))->assertForbidden();
    }
}
