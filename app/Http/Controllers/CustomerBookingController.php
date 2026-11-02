<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Workshop;
use App\Services\Reservation\BookingChangeService;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lets a customer look up their own reservation and move or cancel it.
 */
class CustomerBookingController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /**
     * @var array<string, array<string, string>>
     */
    public const LABELS = [
        'en' => [
            'title' => 'Manage my reservation', 'intro' => 'Enter the reference from your confirmation and the phone number or email you booked with.',
            'reference' => 'Booking reference', 'contact' => 'Phone or email used for the booking', 'find' => 'Find my reservation',
            'not_found' => 'We could not find a reservation with those details. Please check the reference and contact.',
            'service' => 'Service', 'visit' => 'Visit', 'days' => 'days', 'total' => 'Total', 'status' => 'Status',
            'pending' => 'Waiting for the workshop to confirm', 'confirmed' => 'Confirmed', 'completed' => 'Visit completed', 'cancelled' => 'Cancelled',
            'new_dates' => 'Move to other dates', 'drop_off_date' => 'Drop-off', 'pickup_date' => 'Pickup', 'reschedule' => 'Move my visit',
            'cancel' => 'Cancel reservation', 'confirm_cancel' => 'Cancel this reservation? The services are released straight away.',
            'rescheduled' => 'Your visit was moved. The workshop will confirm the new dates.', 'cancelled_ok' => 'Your reservation was cancelled.',
            'unavailable' => 'Those dates are not available for this service. Please try other dates.',
            'not_changeable' => 'This reservation can no longer be changed online. Please contact the workshop.',
            'invalid' => 'Please check this field.', 'drop_off_past' => 'Drop-off cannot be in the past.', 'pickup_after' => 'Pickup cannot be before drop-off.',
            'too_long' => 'Visits are limited to :max days.', 'back' => 'Back to the workshop', 'error' => 'Something went wrong. Please try again.',
        ],
        'id' => [
            'title' => 'Kelola reservasi saya', 'intro' => 'Masukkan kode referensi dari konfirmasi dan nomor telepon atau email yang Anda gunakan saat memesan.',
            'reference' => 'Kode referensi', 'contact' => 'Telepon atau email saat memesan', 'find' => 'Cari reservasi saya',
            'not_found' => 'Reservasi dengan data tersebut tidak ditemukan. Mohon periksa kode referensi dan kontak.',
            'service' => 'Layanan', 'visit' => 'Servis', 'days' => 'hari', 'total' => 'Total', 'status' => 'Status',
            'pending' => 'Menunggu konfirmasi workshop', 'confirmed' => 'Terkonfirmasi', 'completed' => 'Servis selesai', 'cancelled' => 'Dibatalkan',
            'new_dates' => 'Pindah ke tanggal lain', 'drop_off_date' => 'Tanggal masuk', 'pickup_date' => 'Tanggal ambil', 'reschedule' => 'Pindahkan jadwal',
            'cancel' => 'Batalkan reservasi', 'confirm_cancel' => 'Batalkan reservasi ini? Layanan langsung dilepas.',
            'rescheduled' => 'Jadwal servis dipindahkan. Workshop akan mengonfirmasi tanggal baru.', 'cancelled_ok' => 'Reservasi Anda dibatalkan.',
            'unavailable' => 'Tanggal tersebut tidak tersedia untuk layanan ini. Silakan coba tanggal lain.',
            'not_changeable' => 'Reservasi ini tidak bisa diubah lagi secara online. Silakan hubungi bengkel.',
            'invalid' => 'Mohon periksa kolom ini.', 'drop_off_past' => 'Tanggal masuk tidak boleh di masa lalu.', 'pickup_after' => 'Tanggal ambil tidak boleh sebelum tanggal masuk.',
            'too_long' => 'Lama servis dibatasi :max hari.', 'back' => 'Kembali ke workshop', 'error' => 'Terjadi kesalahan. Silakan coba lagi.',
        ],
        'ja' => [
            'title' => 'ご予約の確認・変更', 'intro' => '確認メッセージの予約番号と、ご予約時の電話番号またはメールアドレスを入力してください。',
            'reference' => '予約番号', 'contact' => 'ご予約時の電話番号またはメール', 'find' => '予約を検索',
            'not_found' => '該当するご予約が見つかりません。予約番号と連絡先をご確認ください。',
            'service' => 'サービス', 'visit' => 'ご入庫', 'days' => '泊', 'total' => '合計', 'status' => '状況',
            'pending' => '整備工場の確認待ち', 'confirmed' => '確認済み', 'completed' => 'ご入庫済み', 'cancelled' => 'キャンセル済み',
            'new_dates' => '日程を変更する', 'drop_off_date' => '入庫', 'pickup_date' => '引き取り', 'reschedule' => '日程を変更',
            'cancel' => '予約をキャンセル', 'confirm_cancel' => 'このご予約をキャンセルしますか？サービスはすぐに解放されます。',
            'rescheduled' => '日程を変更しました。整備工場が新しい日程を確認します。', 'cancelled_ok' => 'ご予約をキャンセルしました。',
            'unavailable' => 'この日程ではこのサービスをご利用いただけません。別の日程をお試しください。',
            'not_changeable' => 'このご予約はオンラインでは変更できません。整備工場までご連絡ください。',
            'invalid' => 'この項目をご確認ください。', 'drop_off_past' => '入庫日は過去にできません。', 'pickup_after' => '引き取り日は入庫日以降にしてください。',
            'too_long' => 'ご入庫は最大:max日までです。', 'back' => '整備工場に戻る', 'error' => 'エラーが発生しました。もう一度お試しください。',
        ],
    ];

    public function __construct(private readonly BookingChangeService $changes) {}

    public function page(Request $request, string $workshopSlug): View
    {
        $workshop = $this->publishedWorkshop($workshopSlug);
        $locale = $this->locale($request, $workshop);

        return view('workshop.booking-manage', ['workshop' => $workshop, 'locale' => $locale, 'labels' => self::LABELS[$locale]]);
    }

    public function lookup(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);
        $locale = $this->locale($request, $workshop);
        $booking = $this->findBooking($request, $workshop, $locale);

        return $booking ? response()->json($this->summary($booking, $workshop)) : $this->notFound($locale);
    }

    public function reschedule(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);
        $locale = $this->locale($request, $workshop);
        $labels = self::LABELS[$locale];

        $data = $request->validate([
            'drop_off_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now($workshop->timezone)->toDateString()],
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:drop_off_date'],
        ], ['required' => $labels['invalid'], 'date_format' => $labels['invalid'], 'drop_off_date.after_or_equal' => $labels['drop_off_past'], 'pickup_date.after_or_equal' => $labels['pickup_after']]);

        $booking = $this->findBooking($request, $workshop, $locale);

        if (! $booking) {
            return $this->notFound($locale);
        }

        $dropOffDate = CarbonImmutable::parse($data['drop_off_date'])->startOfDay();
        $pickupDate = CarbonImmutable::parse($data['pickup_date'])->startOfDay();

        if ($dropOffDate->diffInDays($pickupDate) + 1 > ReservationService::MAX_DAYS) {
            return response()->json(['message' => str_replace(':max', (string) ReservationService::MAX_DAYS, $labels['too_long']), 'errors' => ['pickup_date' => [str_replace(':max', (string) ReservationService::MAX_DAYS, $labels['too_long'])]]], 422);
        }

        if (! $this->changes->isChangeable($booking, $workshop)) {
            return response()->json(['message' => $labels['not_changeable']], 409);
        }

        $moved = $this->changes->reschedule($booking, $workshop, $dropOffDate, $pickupDate);

        if (! $moved) {
            return response()->json(['message' => $labels['unavailable'], 'errors' => ['drop_off_date' => [$labels['unavailable']]]], 422);
        }

        return response()->json([...$this->summary($moved->load('serviceType'), $workshop), 'message' => $labels['rescheduled']]);
    }

    public function cancel(Request $request, string $workshopSlug): JsonResponse
    {
        $workshop = $this->publishedWorkshop($workshopSlug);
        $locale = $this->locale($request, $workshop);
        $booking = $this->findBooking($request, $workshop, $locale);

        if (! $booking) {
            return $this->notFound($locale);
        }

        if (! $this->changes->cancel($booking, $workshop)) {
            return response()->json(['message' => self::LABELS[$locale]['not_changeable']], 409);
        }

        return response()->json([...$this->summary($booking->fresh('serviceType'), $workshop), 'message' => self::LABELS[$locale]['cancelled_ok']]);
    }

    private function findBooking(Request $request, Workshop $workshop, string $locale): ?Booking
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:20'],
            'contact' => ['required', 'string', 'max:120'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ], ['required' => self::LABELS[$locale]['invalid']]);

        return $this->changes->findForCustomer($workshop, $data['reference'], $data['contact']);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Booking $booking, Workshop $workshop): array
    {
        return [
            'reference' => $booking->reference,
            'service' => $booking->serviceType->name,
            'vehicles' => $booking->vehicle_count,
            'drop_off_date' => $booking->drop_off_date->toDateString(),
            'pickup_date' => $booking->pickup_date->toDateString(),
            'days' => $booking->days(),
            'vehicle_model' => $booking->vehicle_model,
            'license_plate' => $booking->license_plate,
            'total' => (float) $booking->total_price,
            'currency' => $workshop->currency,
            'status' => $booking->status,
            'changeable' => $this->changes->isChangeable($booking, $workshop),
        ];
    }

    private function notFound(string $locale): JsonResponse
    {
        return response()->json(['message' => self::LABELS[$locale]['not_found']], 404);
    }

    private function locale(Request $request, Workshop $workshop): string
    {
        $locale = $request->input('locale', $request->query('lang'));

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : $workshop->default_locale;
    }

    private function publishedWorkshop(string $workshopSlug): Workshop
    {
        $workshop = Workshop::where('slug', $workshopSlug)->first();

        abort_if(! $workshop || ! $workshop->isPublished(), 404);

        return $workshop;
    }
}
