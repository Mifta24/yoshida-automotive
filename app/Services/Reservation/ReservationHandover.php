<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Workshop;

/**
 * Builds the WhatsApp / phone / email hand-over links that carry a
 * reservation summary (or a plain "talk to staff" request) to the workshop team.
 */
class ReservationHandover
{
    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forBooking(Workshop $workshop, Booking $booking, string $locale): array
    {
        $message = $this->reservationMessage($booking, $locale);

        return $this->links($workshop, $message, $this->subject('reservation', $locale).' '.$booking->reference);
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    public function forStaff(Workshop $workshop, string $locale): array
    {
        return $this->links($workshop, $this->subject('staff_message', $locale), $this->subject('staff', $locale));
    }

    /**
     * @return array{whatsapp_url: ?string, phone_url: ?string, email_url: ?string}
     */
    private function links(Workshop $workshop, string $message, string $subject): array
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $workshop->whatsapp);
        $phone = preg_replace('/[^\d+]/', '', (string) $workshop->phone);

        return [
            'whatsapp_url' => $whatsapp !== '' ? 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message) : null,
            'phone_url' => $phone !== '' ? 'tel:'.$phone : null,
            'email_url' => filled($workshop->email) ? 'mailto:'.$workshop->email.'?subject='.rawurlencode($subject).'&body='.rawurlencode($message) : null,
        ];
    }

    private function reservationMessage(Booking $booking, string $locale): string
    {
        $booking->loadMissing('serviceType');

        $labels = match ($locale) {
            'en' => ['Hello, I would like to request a reservation.', 'Name', 'Drop-off', 'Pickup', 'Customers', 'Services', 'Service Type', 'Special Request', 'Reference'],
            'ja' => ['こんにちは。入庫のご予約をリクエストしたいです。', 'お名前', '入庫', '引き取り', '人数', 'サービス数', 'サービスタイプ', 'ご要望', '予約番号'],
            default => ['Halo, saya ingin mengajukan reservasi.', 'Nama', 'Drop-off', 'Pickup', 'Pelanggan', 'Layanan', 'Tipe Layanan', 'Permintaan Khusus', 'Referensi'],
        };

        $customers = $booking->adults + $booking->children;

        return implode("\n", [
            $labels[0],
            '',
            "{$labels[1]}: {$booking->customer_name}",
            "{$labels[2]}: ".$booking->drop_off_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[3]}: ".$booking->pickup_date->locale($locale)->translatedFormat('j F Y'),
            "{$labels[4]}: {$customers}",
            "{$labels[5]}: {$booking->vehicle_count}",
            "{$labels[6]}: ".$booking->serviceType->translatedName($locale),
            "{$labels[7]}: ".($booking->notes ?: '-'),
            '',
            "{$labels[8]}: {$booking->reference}",
        ]);
    }

    private function subject(string $key, string $locale): string
    {
        return [
            'en' => ['reservation' => 'Reservation request', 'staff' => 'Question for workshop staff', 'staff_message' => 'Hello, I would like to speak with workshop staff.'],
            'ja' => ['reservation' => '予約リクエスト', 'staff' => 'スタッフへのご相談', 'staff_message' => 'こんにちは。整備工場のスタッフとお話ししたいです。'],
            'id' => ['reservation' => 'Permintaan reservasi', 'staff' => 'Pertanyaan untuk staf workshop', 'staff_message' => 'Halo, saya ingin bicara dengan staf bengkel.'],
        ][$locale][$key];
    }
}
