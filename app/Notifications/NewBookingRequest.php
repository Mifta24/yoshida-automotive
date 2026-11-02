<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\SentOnBehalfOfWorkshop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewBookingRequest extends Notification implements ShouldQueue
{
    use Queueable, SentOnBehalfOfWorkshop;

    public function __construct(public Booking $booking)
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
        $contact = $booking->customer_phone ?? $booking->customer_email;

        return $this->fromWorkshop((new MailMessage), $booking->workshop)
            ->subject("New reservation request {$booking->reference}")
            ->line("{$booking->customer_name} asked to book {$booking->vehicle_count} × {$booking->serviceType->name}.")
            ->line("Visit: {$booking->drop_off_date->toFormattedDateString()} → {$booking->pickup_date->toFormattedDateString()} · {$booking->vehicle_model} ({$booking->license_plate})")
            ->line("Contact ({$booking->contact_type}): {$contact}")
            ->when($booking->notes, fn (MailMessage $mail) => $mail->line("Request: {$booking->notes}"))
            ->action('Review bookings', route('admin.bookings.index', ['status' => Booking::STATUS_PENDING]));
    }
}
