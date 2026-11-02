<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Workshop;
use App\Services\Advisor\AdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdvisorChatController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    public function __construct(private readonly AdvisorService $advisor) {}

    public function start(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);

        $locale = in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $workshop->default_locale;

        $conversation = $this->advisor->startConversation($workshop, $locale);

        return response()->json([
            'customer_token' => $conversation->customer_token,
            'locale' => $conversation->locale,
        ]);
    }

    public function message(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);

        $data = $request->validate([
            'customer_token' => ['required', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'scene' => ['nullable', Rule::in(Conversation::SCENES)],
            'selected_service' => ['nullable', 'string', 'max:120'],
            'selected_facility' => ['nullable', 'integer', 'min:1'],
            'reservation' => ['nullable', 'array'],
            'reservation.drop_off_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.pickup_date' => ['nullable', 'date_format:Y-m-d'],
            'reservation.vehicle_model' => ['nullable', 'string', 'max:100'],
            'reservation.license_plate' => ['nullable', 'string', 'max:20'],
            'reservation.vehicles' => ['nullable', 'integer', 'min:1', 'max:10'],
            'reservation.service_type_slug' => ['nullable', 'string', 'max:120'],
        ]);

        $conversation = $this->findConversation($workshop, $data['customer_token']);

        $this->advisor->rememberContext($workshop, $conversation, [
            'scene' => $data['scene'] ?? null,
            'selected_facility' => $data['selected_facility'] ?? null,
            'selected_service' => $data['selected_service'] ?? ($data['reservation']['service_type_slug'] ?? null),
            ...array_key_exists('reservation', $data) ? ['reservation' => array_filter($data['reservation'] ?? [], fn ($value) => $value !== null && $value !== '')] : [],
        ]);

        try {
            $assistantMessage = $this->advisor->reply($workshop, $conversation, $data['message']);
        } catch (\Throwable $e) {
            Log::error('Advisor reply failed', ['workshop_id' => $workshop->id, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'advisor_unavailable',
                'message' => 'The AI Advisor is temporarily unavailable. Please try again in a moment.',
            ], 503);
        }

        return response()->json([
            'message' => $this->formatMessage($assistantMessage),
            'status' => $conversation->fresh()->status,
        ]);
    }

    /**
     * The customer's thumbs up (1) or down (-1) on one of the advisor's replies; 0 takes it back.
     */
    public function feedback(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);

        $data = $request->validate([
            'customer_token' => ['required', 'uuid'],
            'message_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'in:-1,0,1'],
        ]);

        $conversation = $this->findConversation($workshop, $data['customer_token']);

        $message = $conversation->messages()->where('role', ConversationMessage::ROLE_ASSISTANT)->findOrFail($data['message_id']);
        $message->update(['rating' => $data['rating'] === 0 ? null : $data['rating']]);

        return response()->json(['id' => $message->id, 'rating' => $message->rating]);
    }

    public function history(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);

        $data = $request->validate(['customer_token' => ['required', 'uuid']]);

        $conversation = $this->findConversation($workshop, $data['customer_token']);

        $messages = $conversation->messages()
            ->whereIn('role', [ConversationMessage::ROLE_CUSTOMER, ConversationMessage::ROLE_ASSISTANT, ConversationMessage::ROLE_STAFF, ConversationMessage::ROLE_SYSTEM])
            ->reorder()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ConversationMessage $m) => $this->formatMessage($m))
            ->values();

        return response()->json([
            'messages' => $messages,
            'status' => $conversation->status,
        ]);
    }

    private function publishedWorkshop(string $workshopSlug): Workshop
    {
        $workshop = Workshop::where('slug', $workshopSlug)->first();

        abort_if(! $workshop || ! $workshop->isPublished(), 404);

        return $workshop;
    }

    private function findConversation(Workshop $workshop, string $customerToken): Conversation
    {
        $conversation = Conversation::where('workshop_id', $workshop->id)
            ->where('customer_token', $customerToken)
            ->first();

        if (! $conversation) {
            throw ValidationException::withMessages([
                'customer_token' => 'This conversation no longer exists. Please start a new one.',
            ]);
        }

        return $conversation;
    }

    private function formatMessage(ConversationMessage $message): array
    {
        return [
            'id' => $message->id,
            'rating' => $message->rating,
            'role' => $message->role,
            'content' => $message->content,
            'ui_payload' => $message->ui_payload,
            'suggested_actions' => $this->suggestedActions($message->ui_payload),
            'quick_replies' => $this->quickReplies($message),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    /**
     * Tappable answers under an assistant reply, so the customer can carry on
     * without typing. The page turns each key into its words in the customer's language.
     *
     * @return list<string>
     */
    private function quickReplies(ConversationMessage $message): array
    {
        if ($message->role !== ConversationMessage::ROLE_ASSISTANT) {
            return [];
        }

        $payloads = collect($message->ui_payload ?? []);
        $types = $payloads->pluck('type');

        if ($types->intersect(['handover', 'booking_form', 'visit_picker', 'tour_start'])->isNotEmpty()) {
            return [];
        }

        if ($types->contains('service_results')) {
            return $payloads->firstWhere('type', 'service_results')['drop_off_date'] ?? null
                ? ['cheapest', 'family', 'view']
                : ['pick_dates', 'cheapest', 'family', 'view'];
        }

        if ($types->intersect(['service_detail', 'availability', 'service_photos'])->isNotEmpty()) {
            return ['pick_dates', 'services'];
        }

        if ($types->contains('facility_results')) {
            return ['location', 'menu', 'services'];
        }

        if ($types->contains('price_list')) {
            return ['facilities', 'location', 'services'];
        }

        if ($types->contains('location')) {
            return ['facilities', 'services', 'tour'];
        }

        if ($types->contains('booking_confirmation')) {
            return ['facilities', 'policies', 'tour'];
        }

        return filled($message->content) ? ['services', 'facilities', 'location', 'policies', 'tour'] : [];
    }

    /**
     * Interface actions that follow from what the advisor just showed, so
     * the customer can step into the matching scene instead of typing again.
     *
     * @param  list<array<string, mixed>>|null  $uiPayload
     * @return list<array{action: string, service?: string}>
     */
    private function suggestedActions(?array $uiPayload): array
    {
        $actions = [];

        foreach ($uiPayload ?? [] as $payload) {
            $type = $payload['type'] ?? null;
            $service = $payload['service']['service_type_slug'] ?? $payload['quote']['service_type_slug'] ?? null;

            if ($type === 'service_detail' && $service) {
                $actions[] = ['action' => 'view_service', 'service' => $service];
                $actions[] = ['action' => 'reserve', 'service' => $service];
            } elseif ($type === 'availability' && ($payload['available'] ?? false) && $service) {
                $actions[] = ['action' => 'reserve', 'service' => $service];
            } elseif ($type === 'handover') {
                $actions[] = ['action' => 'staff'];
            }
        }

        return collect($actions)->unique(fn (array $action) => $action['action'].($action['service'] ?? ''))->values()->all();
    }
}
