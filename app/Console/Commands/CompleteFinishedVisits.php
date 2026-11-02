<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Workshop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:complete-visits')]
#[Description('Mark confirmed reservations as completed once their pickup day has come')]
class CompleteFinishedVisits extends Command
{
    public function handle(): int
    {
        $completed = 0;

        Workshop::query()->each(function (Workshop $workshop) use (&$completed): void {
            Booking::where('workshop_id', $workshop->id)
                ->where('status', Booking::STATUS_CONFIRMED)
                ->whereDate('pickup_date', '<', now($workshop->timezone)->toDateString())
                ->lazyById(100)
                ->each(function (Booking $booking) use ($workshop, &$completed): void {
                    $booking->update(['status' => Booking::STATUS_COMPLETED]);
                    AuditLog::record($workshop->id, 'booking.completed', 'The visit is over, so the booking was marked completed', $booking, null, [], AuditLog::ACTOR_SYSTEM);
                    $completed++;
                });
        });

        $this->info("Completed {$completed} finished visit(s).");

        return self::SUCCESS;
    }
}
