<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ServiceType;
use App\Models\Workshop;
use App\Services\Advisor\WorkshopAdvisorTools;
use App\Services\Reservation\MemberBookings;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /**
     * Customer-facing validation messages, keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'en' => [
            'invalid' => 'Please check this field.',
            'drop_off_past' => 'Drop-off cannot be in the past.',
            'pickup_after' => 'Pickup cannot be before drop-off.',
            'too_long' => 'Visits are limited to :max days. Please contact workshop staff for longer visits.',
            'service_unknown' => 'Please choose a service.',
            'unavailable' => 'This service is not available for those dates.',
            'contact_email' => 'Please enter a valid email address.',
            'contact_phone' => 'Please enter a valid phone or WhatsApp number.',
        ],
        'id' => [
            'invalid' => 'Mohon periksa kolom ini.',
            'drop_off_past' => 'Tanggal masuk tidak boleh di masa lalu.',
            'pickup_after' => 'Tanggal ambil tidak boleh sebelum tanggal masuk.',
            'too_long' => 'Lama servis dibatasi :max hari. Hubungi staf bengkel untuk servis lebih lama.',
            'service_unknown' => 'Silakan pilih layanan.',
            'unavailable' => 'Layanan ini tidak tersedia pada tanggal tersebut.',
            'contact_email' => 'Masukkan alamat email yang valid.',
            'contact_phone' => 'Masukkan nomor telepon atau WhatsApp yang valid.',
        ],
        'ja' => [
            'invalid' => 'この項目をご確認ください。',
            'drop_off_past' => '入庫日は過去にできません。',
            'pickup_after' => '引き取り日は入庫日以降にしてください。',
            'too_long' => 'ご入庫は最大:max日までです。それ以上はスタッフにご相談ください。',
            'service_unknown' => 'サービスを選択してください。',
            'unavailable' => 'この日程では、このサービスはご利用いただけません。',
            'contact_email' => '有効なメールアドレスを入力してください。',
            'contact_phone' => '有効な電話番号またはWhatsApp番号を入力してください。',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
        private readonly MemberBookings $memberBookings,
    ) {}

    public function quote(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);
        $locale = $this->locale($request, $workshop);

        $data = $request->validate($this->visitRules($workshop), $this->validationMessages($locale));
        [$serviceType, $dropOffDate, $pickupDate, $vehicles, $towing] = $this->resolveVisit($workshop, $data, $locale);

        $discount = $this->memberBookings->discountFor($workshop, $this->memberBookings->signedIn($workshop));
        $quote = $this->reservations->quote($serviceType, $dropOffDate, $pickupDate, $vehicles, $towing, discountPercent: $discount);

        if (! $quote) {
            return $this->unavailable($workshop, $serviceType, $data, $locale);
        }

        return response()->json([
            'available' => true,
            'days' => $quote['days'],
            'service_total' => $quote['service_total'],
            'towing_total' => $quote['towing_total'],
            'subtotal' => $quote['subtotal'],
            'member_discount_percent' => $quote['discount_percent'],
            'discount_amount' => $quote['discount_amount'],
            'grand_total' => $quote['grand_total'],
            'currency' => $workshop->currency,
        ]);
    }

    public function store(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);
        $locale = $this->locale($request, $workshop);
        $messages = self::MESSAGES[$locale];

        $data = $request->validate([
            ...$this->visitRules($workshop),
            'vehicle_model' => ['required', 'string', 'max:100'],
            'license_plate' => ['required', 'string', 'max:20'],
            'customer_name' => ['required', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'customer_token' => ['nullable', 'uuid'],
            'via_chat' => ['nullable', 'boolean'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(ReservationService::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$serviceType, $dropOffDate, $pickupDate, $vehicles, $towing] = $this->resolveVisit($workshop, $data, $locale);

        $conversation = isset($data['customer_token'])
            ? Conversation::where('workshop_id', $workshop->id)->where('customer_token', $data['customer_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($workshop, $serviceType, [
            'drop_off_date' => $dropOffDate,
            'pickup_date' => $pickupDate,
            'vehicle_model' => $data['vehicle_model'],
            'license_plate' => $data['license_plate'],
            'vehicles' => $vehicles,
            'towing' => $towing,
            'customer_name' => $data['customer_name'],
            'customer_email' => $isEmail ? $data['contact_value'] : null,
            'customer_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'notes' => $data['special_request'] ?? null,
            'member_id' => $this->memberBookings->signedIn($workshop)?->id,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($workshop, $serviceType, $data, $locale);
        }

        $handover = $this->handover->forBooking($workshop, $booking, $locale);

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'currency' => $workshop->currency,
            'handover' => $handover,
            'confirmation' => $conversation && ($data['via_chat'] ?? false)
                ? $this->recordInChat($conversation, $booking, $serviceType, $workshop, $locale, $handover)
                : null,
        ], 201);
    }

    /**
     * Keeps a booking made with the taps of the in-chat form in the
     * conversation, so the confirmation is still there when the customer comes back.
     *
     * @param  array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}  $handover
     * @return array<string, mixed>
     */
    private function recordInChat(Conversation $conversation, Booking $booking, ServiceType $serviceType, Workshop $workshop, string $locale, array $handover): array
    {
        $confirmation = [...WorkshopAdvisorTools::bookingConfirmation($booking, $serviceType, $locale, $workshop->currency), 'handover' => $handover];

        $conversation->messages()->create([
            'role' => ConversationMessage::ROLE_ASSISTANT,
            'ui_payload' => [$confirmation],
        ]);
        $conversation->update(['last_message_at' => now()]);

        return $confirmation;
    }

    /**
     * @return array<string, mixed>
     */
    private function visitRules(Workshop $workshop): array
    {
        $today = now($workshop->timezone)->toDateString();

        return [
            'service_type_slug' => ['required', 'string', 'max:120'],
            'drop_off_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'pickup_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:drop_off_date'],
            'vehicles' => ['required', 'integer', 'min:1', 'max:10'],
            'towing' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'boolean' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'drop_off_date.after_or_equal' => $messages['drop_off_past'],
            'pickup_date.after_or_equal' => $messages['pickup_after'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ServiceType, 1: CarbonImmutable, 2: CarbonImmutable, 3: int, 4: bool}
     */
    private function resolveVisit(Workshop $workshop, array $data, string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        $serviceType = $workshop->serviceTypes()->where('is_active', true)->where('slug', $data['service_type_slug'])->first();

        if (! $serviceType) {
            throw ValidationException::withMessages(['service_type_slug' => $messages['service_unknown']]);
        }

        $dropOffDate = CarbonImmutable::parse($data['drop_off_date'])->startOfDay();
        $pickupDate = CarbonImmutable::parse($data['pickup_date'] ?? $data['drop_off_date'])->startOfDay();

        if ($dropOffDate->diffInDays($pickupDate) + 1 > ReservationService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'pickup_date' => str_replace(':max', (string) ReservationService::MAX_DAYS, $messages['too_long']),
            ]);
        }

        return [$serviceType, $dropOffDate, $pickupDate, (int) $data['vehicles'], (bool) ($data['towing'] ?? false)];
    }

    /**
     * The service cannot be booked for those days: say so, and offer the service
     * types that are free on the same dates.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Workshop $workshop, ServiceType $requested, array $data, string $locale): JsonResponse
    {
        $dropOffDate = CarbonImmutable::parse($data['drop_off_date'])->startOfDay();
        $pickupDate = CarbonImmutable::parse($data['pickup_date'] ?? $data['drop_off_date'])->startOfDay();
        $vehicles = (int) $data['vehicles'];
        $discount = $this->memberBookings->discountFor($workshop, $this->memberBookings->signedIn($workshop));

        $alternatives = $workshop->serviceTypes()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->map(function (ServiceType $serviceType) use ($dropOffDate, $pickupDate, $vehicles, $locale, $discount) {
                $quote = $this->reservations->quote($serviceType, $dropOffDate, $pickupDate, $vehicles, discountPercent: $discount);

                return $quote ? [
                    'slug' => $serviceType->slug,
                    'name' => $serviceType->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = self::MESSAGES[$locale]['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['service_type_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $workshop->currency,
        ], 422);
    }

    private function locale(Request $request, Workshop $workshop): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $workshop->default_locale;
    }

    private function publishedWorkshop(string $workshopSlug): Workshop
    {
        $workshop = Workshop::where('slug', $workshopSlug)->first();

        abort_if(! $workshop || ! $workshop->isPublished(), 404);

        return $workshop;
    }
}
