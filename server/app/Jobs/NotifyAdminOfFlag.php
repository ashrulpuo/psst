<?php

namespace App\Jobs;

use App\Notifications\FlaggedMessageAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Email the owner that a message was flagged. Runs after the message transaction commits, and is best-effort:
 * a mail outage must never surface to the sender or undo the flag.
 */
class NotifyAdminOfFlag implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): void
    {
        $email = (string) config('psst.moderation.alert_email');

        if ($email === '') {
            return;
        }

        try {
            Notification::route('mail', $email)->notify(new FlaggedMessageAlert);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
