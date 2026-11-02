<?php

namespace App\Http\Controllers\Member;

use App\Core\Member\Models\Member;
use App\Core\Member\Support\MemberLabels;
use App\Http\Controllers\Concerns\ResolvesMemberPages;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Workshop;
use App\Services\Reservation\BookingChangeService;
use App\Services\Reservation\MemberBookings;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Moving or cancelling a visit from the member's own page — no reference and
 * contact to type, because the booking is already theirs.
 */
class BookingController extends Controller
{
    use ResolvesMemberPages;

    public function __construct(
        private readonly MemberBookings $memberBookings,
        private readonly BookingChangeService $changes,
    ) {}

    public function reschedule(Request $request, string $workshopSlug, string $reference): RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $member = $request->user(Member::GUARD);
        $locale = $this->memberLocale($request, $workshop, $member);
        $labels = MemberLabels::for($locale);
        $booking = $this->ownBooking($member, $workshop, $reference);

        $data = $request->validate([
            'drop_off_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now($workshop->timezone)->toDateString()],
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:drop_off_date'],
        ], ['required' => $labels['invalid'], 'date_format' => $labels['invalid'], 'drop_off_date.after_or_equal' => $labels['drop_off_past'], 'pickup_date.after_or_equal' => $labels['pickup_after']]);

        $dropOffDate = CarbonImmutable::parse($data['drop_off_date'])->startOfDay();
        $pickupDate = CarbonImmutable::parse($data['pickup_date'])->startOfDay();

        if ($dropOffDate->diffInDays($pickupDate) + 1 > ReservationService::MAX_DAYS) {
            return $this->back($workshop, $locale)->with('error', MemberLabels::fill($labels['too_long'], ['max' => ReservationService::MAX_DAYS]));
        }

        if (! $this->changes->isChangeable($booking, $workshop)) {
            return $this->back($workshop, $locale)->with('error', $labels['not_changeable']);
        }

        $moved = $this->changes->reschedule($booking, $workshop, $dropOffDate, $pickupDate);

        return $moved
            ? $this->back($workshop, $locale)->with('status', $labels['rescheduled'])
            : $this->back($workshop, $locale)->with('error', $labels['unavailable']);
    }

    public function cancel(Request $request, string $workshopSlug, string $reference): RedirectResponse
    {
        $workshop = $this->memberWorkshop($request);
        $member = $request->user(Member::GUARD);
        $locale = $this->memberLocale($request, $workshop, $member);
        $labels = MemberLabels::for($locale);
        $booking = $this->ownBooking($member, $workshop, $reference);

        return $this->changes->cancel($booking, $workshop)
            ? $this->back($workshop, $locale)->with('status', $labels['cancelled_ok'])
            : $this->back($workshop, $locale)->with('error', $labels['not_changeable']);
    }

    /**
     * Only a booking made under this member: someone else's reference is a 404.
     */
    private function ownBooking(Member $member, Workshop $workshop, string $reference): Booking
    {
        return $this->memberBookings->forMember($member, $workshop)
            ->where('reference', strtoupper(trim($reference)))
            ->firstOrFail();
    }

    private function back(Workshop $workshop, string $locale): RedirectResponse
    {
        return redirect()->route('member.account', ['workshopSlug' => $workshop->slug, 'lang' => $locale]);
    }
}
