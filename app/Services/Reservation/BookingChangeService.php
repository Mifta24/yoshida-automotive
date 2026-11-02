<?php

namespace App\Services\Reservation;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Workshop;
use App\Notifications\CustomerChangedBooking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * What a customer may do to a reservation they made: find it again with its
 * reference and the contact they gave, move it to other dates, or cancel it.
 * Every change re-checks availability and tells the workshop.
 */
class BookingChangeService
{
    public function __construct(private readonly ReservationService $reservations) {}

    /**
     * The booking only if the contact matches the one left when booking, so a
     * reference alone (printed, forwarded, guessed) is never enough.
     */
    public function findForCustomer(Workshop $workshop, string $reference, string $contact): ?Booking
    {
        $booking = Booking::where('workshop_id', $workshop->id)
            ->where('reference', strtoupper(trim($reference)))
            ->with('serviceType')
            ->first();

        return $booking && $this->contactMatches($booking, $contact) ? $booking : null;
    }

    /**
     * Whether a customer can still change this booking: it must be live and not yet started.
     */
    public function isChangeable(Booking $booking, Workshop $workshop): bool
    {
        return in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)
            && $booking->drop_off_date->toDateString() >= now($workshop->timezone)->toDateString();
    }

    /**
     * Moves the visit to new dates, keeping the service and vehicle. A confirmed
     * booking goes back to pending, because the workshop agreed to other days.
     * Returns null when the new days are not available (nothing changes).
     */
    public function reschedule(Booking $booking, Workshop $workshop, CarbonImmutable $dropOffDate, CarbonImmutable $pickupDate): ?Booking
    {
        try {
            $moved = $this->move($booking, $workshop, $dropOffDate, $pickupDate);
        } catch (\DomainException) {
            return null;
        }

        if ($moved) {
            $this->reservations->notifyStaff($workshop, new CustomerChangedBooking($moved, 'rescheduled'));
        }

        return $moved;
    }

    /**
     * @throws \DomainException when the new days are not available; the transaction is then undone
     */
    private function move(Booking $booking, Workshop $workshop, CarbonImmutable $dropOffDate, CarbonImmutable $pickupDate): ?Booking
    {
        return DB::transaction(function () use ($booking, $workshop, $dropOffDate, $pickupDate): ?Booking {
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $this->isChangeable($current, $workshop)) {
                return null;
            }

            $this->reservations->releaseInventory($current);

            $quote = $this->reservations->quote($current->serviceType, $dropOffDate, $pickupDate, $current->vehicle_count, $current->towing, lockForUpdate: true, discountPercent: (float) $current->discount_percent);

            if (! $quote) {
                throw new \DomainException('Not available');
            }

            $from = $current->drop_off_date->toDateString().' → '.$current->pickup_date->toDateString();

            $current->update([
                'drop_off_date' => $dropOffDate->toDateString(),
                'pickup_date' => $pickupDate->toDateString(),
                'total_price' => $quote['grand_total'],
                'discount_amount' => $quote['discount_amount'],
                'status' => Booking::STATUS_PENDING,
            ]);
            $this->reservations->holdInventory($current);

            AuditLog::record($workshop->id, 'booking.rescheduled', "Customer moved the visit from {$from} to {$dropOffDate->toDateString()} → {$pickupDate->toDateString()}", $current, null, ['from' => $from], AuditLog::ACTOR_CUSTOMER);

            return $current;
        });
    }

    public function cancel(Booking $booking, Workshop $workshop): bool
    {
        $cancelled = DB::transaction(function () use ($booking, $workshop): ?Booking {
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $this->isChangeable($current, $workshop)) {
                return null;
            }

            $this->reservations->releaseInventory($current);
            $current->update(['status' => Booking::STATUS_CANCELLED]);

            AuditLog::record($workshop->id, 'booking.cancelled', 'Customer cancelled the booking', $current, null, [], AuditLog::ACTOR_CUSTOMER);

            return $current;
        });

        if ($cancelled) {
            $this->reservations->notifyStaff($workshop, new CustomerChangedBooking($cancelled, 'cancelled'));
        }

        return $cancelled !== null;
    }

    private function contactMatches(Booking $booking, string $contact): bool
    {
        $contact = trim($contact);

        if (str_contains($contact, '@')) {
            return $booking->customer_email !== null && strcasecmp($booking->customer_email, $contact) === 0;
        }

        $given = preg_replace('/\D+/', '', $contact);
        $stored = preg_replace('/\D+/', '', (string) $booking->customer_phone);

        // Compare the last nine digits so +62 811… and 0811… are the same number.
        return strlen($given) >= 6 && strlen($stored) >= 6 && substr($given, -9) === substr($stored, -9);
    }
}
