<?php

namespace App\Services\Reservation;

use App\Core\Member\Models\Member;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ServiceInventory;
use App\Models\ServiceType;
use App\Models\Workshop;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The single place where visit prices, availability and reservation requests
 * are computed. Both the AI advisor tools and the customer-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    public const MAX_DAYS = 30;

    public const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    public function __construct(private readonly MemberBookings $memberBookings = new MemberBookings) {}

    /**
     * Prices a visit: one flat price per vehicle, taken from the drop-off day, while
     * the vehicle holds a workshop slot on every day up to and including pickup.
     * Same-day service is one day. Returns null if any day lacks enough free slots:
     * this is the one place price and availability truth comes from, never the model.
     *
     * @return array{days: int, service_total: float, towing_total: float, subtotal: float, discount_percent: float, discount_amount: float, grand_total: float, min_available_units: int}|null
     */
    public function quote(
        ServiceType $serviceType,
        CarbonImmutable $dropOffDate,
        CarbonImmutable $pickupDate,
        int $vehicles = 1,
        bool $towing = false,
        bool $lockForUpdate = false,
        float $discountPercent = 0.0,
    ): ?array {
        $days = (int) $dropOffDate->diffInDays($pickupDate) + 1;

        if ($days < 1) {
            return null;
        }

        $query = ServiceInventory::where('service_type_id', $serviceType->id)
            ->whereDate('service_date', '>=', $dropOffDate->toDateString())
            ->whereDate('service_date', '<=', $pickupDate->toDateString())
            ->orderBy('service_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        // Closed days inside a visit have no row and hold no slot, but the workshop
        // must be open on the days the vehicle is brought in and collected.
        if ($inventory->first()?->service_date->toDateString() !== $dropOffDate->toDateString()
            || $inventory->last()->service_date->toDateString() !== $pickupDate->toDateString()) {
            return null;
        }

        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $day) {
            if ($day->availableUnits() < $vehicles) {
                return null;
            }

            $minAvailable = min($minAvailable, $day->availableUnits());
        }

        $serviceTotal = (float) $inventory->first()->price * $vehicles;
        $towingTotal = $towing && $serviceType->towing_available
            ? (float) $serviceType->towing_price * $vehicles
            : 0.0;

        $subtotal = $serviceTotal + $towingTotal;
        $discountAmount = round($subtotal * $discountPercent / 100, 2);

        return [
            'days' => $days,
            'service_total' => $serviceTotal,
            'towing_total' => $towingTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'grand_total' => $subtotal - $discountAmount,
            'min_available_units' => $minAvailable,
        ];
    }

    /**
     * Creates a pending reservation request and holds the inventory for it.
     * Returns null when the service no longer has free slots on those days.
     *
     * @param  array{drop_off_date: CarbonImmutable, pickup_date: CarbonImmutable, vehicle_model: string, license_plate: string, vehicles?: int, towing?: bool, customer_name: string, customer_email?: ?string, customer_phone?: ?string, contact_type?: ?string, notes?: ?string, member_id?: ?int}  $data
     */
    public function createRequest(Workshop $workshop, ServiceType $serviceType, array $data, ?Conversation $conversation = null): ?Booking
    {
        $vehicles = $data['vehicles'] ?? 1;
        $towing = (bool) ($data['towing'] ?? false);

        $member = filled($data['member_id'] ?? null) ? Member::find($data['member_id']) : null;
        $discountPercent = $this->memberBookings->discountFor($workshop, $member);

        $booking = DB::transaction(function () use ($workshop, $serviceType, $data, $conversation, $vehicles, $towing, $discountPercent) {
            $quote = $this->quote($serviceType, $data['drop_off_date'], $data['pickup_date'], $vehicles, $towing, lockForUpdate: true, discountPercent: $discountPercent);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'workshop_id' => $workshop->id,
                'service_type_id' => $serviceType->id,
                'conversation_id' => $conversation?->id,
                'member_id' => $data['member_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'drop_off_date' => $data['drop_off_date']->toDateString(),
                'pickup_date' => $data['pickup_date']->toDateString(),
                'vehicle_model' => $data['vehicle_model'],
                'license_plate' => $data['license_plate'],
                'vehicle_count' => $vehicles,
                'towing' => $towing && $serviceType->towing_available,
                'total_price' => $quote['grand_total'],
                'discount_percent' => $quote['discount_percent'],
                'discount_amount' => $quote['discount_amount'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $this->notifyStaff($workshop, new NewBookingRequest($booking));
        }

        return $booking;
    }

    /**
     * A mail problem must never undo or hide a reservation the customer has already made.
     */
    public function notifyStaff(Workshop $workshop, Notification $notification): void
    {
        try {
            NotificationFacade::send($workshop->notifiableStaff(), $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Gives the units held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    /**
     * Takes the booking's slots out of the inventory again (used when its visit moves).
     */
    public function holdInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, +1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        ServiceInventory::where('service_type_id', $booking->service_type_id)
            ->whereDate('service_date', '>=', $booking->drop_off_date->toDateString())
            ->whereDate('service_date', '<=', $booking->pickup_date->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_units', $booking->vehicle_count);
    }
}
