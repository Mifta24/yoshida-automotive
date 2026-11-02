<?php

namespace App\Notifications\Concerns;

use App\Models\Workshop;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mail to a workshop's team arrives under that workshop's name, and a reply goes to
 * the workshop's own address — the sending account is shared, the identity is not.
 */
trait SentOnBehalfOfWorkshop
{
    protected function fromWorkshop(MailMessage $mail, Workshop $workshop): MailMessage
    {
        $mail->from((string) config('mail.from.address'), $workshop->name);

        return filled($workshop->email) ? $mail->replyTo($workshop->email, $workshop->name) : $mail;
    }
}
