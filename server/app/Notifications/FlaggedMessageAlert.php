<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the owner something needs review. Deliberately carries no message text, sender or recipient:
 * the email is not a safe place for message content.
 */
class FlaggedMessageAlert extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('A message was flagged')
            ->line('New messages were flagged for review.')
            ->action('Open the flagged messages', rtrim((string) config('app.url'), '/').'/admin/flags');
    }
}
