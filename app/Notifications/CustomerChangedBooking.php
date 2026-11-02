<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\SentOnBehalfOfWorkshop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerChangedBooking extends Notification implements ShouldQueue
{
    use Queueable, SentOnBehalfOfWorkshop;

    /**
     * @param  'rescheduled'|'cancelled'  $change
     */
    public function __construct(public Booking $booking, public string $change)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $visit = "{$booking->drop_off_date->toFormattedDateString()} → {$booking->pickup_date->toFormattedDateString()}";

        return $this->fromWorkshop((new MailMessage), $booking->workshop)
            ->subject("Customer {$this->change} booking {$booking->reference}")
            ->line($this->change === 'cancelled'
                ? "{$booking->customer_name} cancelled booking {$booking->reference}. The services are free again."
                : "{$booking->customer_name} moved booking {$booking->reference} to {$visit}. It is waiting for your confirmation again.")
            ->action('Open the booking', route('admin.bookings.show', $booking));
    }
}
