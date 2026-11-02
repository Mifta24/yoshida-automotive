<?php

namespace App\Services\Reservation;

use App\Core\Member\Models\Member;
use App\Models\Booking;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The workshop's side of the member core: which bookings belong to a member.
 */
class MemberBookings
{
    /**
     * The member signed in on this workshop in the current session, if the workshop has the add-on.
     */
    public function signedIn(Workshop $workshop): ?Member
    {
        $member = Auth::guard(Member::GUARD)->user();

        return $workshop->supportsMembers() && $member?->belongsToTenant($workshop) ? $member : null;
    }

    /**
     * The percent off a member gets today: the discount of the tier their points
     * have reached. Zero for customers, for another workshop's members and when the
     * workshop has no loyalty add-on. Fixed on a booking when it is made.
     */
    public function discountFor(Workshop $workshop, ?Member $member): float
    {
        $program = $workshop->loyaltyProgram();

        return $member && $program && $member->belongsToTenant($workshop) ? $program->discountFor($member->pointsBalance()) : 0.0;
    }

    /**
     * A member's reservations at the workshop, the next visit first.
     *
     * @return Builder<Booking>
     */
    public function forMember(Member $member, Workshop $workshop): Builder
    {
        return Booking::where('workshop_id', $workshop->id)
            ->where('member_id', $member->id)
            ->with('serviceType')
            ->orderByDesc('drop_off_date')
            ->orderByDesc('id');
    }

    /**
     * Claims the workshop's earlier, unclaimed bookings made with the member's
     * email. The email was proven at sign-in, so the match is safe; phone
     * numbers are not, and are never used to link.
     */
    public function adopt(Member $member, Workshop $workshop): int
    {
        return Booking::where('workshop_id', $workshop->id)
            ->whereNull('member_id')
            ->whereRaw('LOWER(customer_email) = ?', [$member->email])
            ->update(['member_id' => $member->id]);
    }
}
