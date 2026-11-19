<?php

namespace App\Moderation;

use App\Jobs\NotifyAdminOfFlag;
use App\Models\Message;
use App\Models\MessageFlag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class FlagInbound
{
    public const ALERT_KEY = 'psst:flag-alert';

    public function __construct(private readonly KeywordFlagger $flagger) {}

    /** Record a keyword hit. The message is delivered regardless; this only leaves a note for review. */
    public function handle(Message $message, string $body): ?MessageFlag
    {
        $terms = $this->flagger->matches($body);

        if ($terms === []) {
            return null;
        }

        $flag = MessageFlag::create(['message_id' => $message->id, 'terms' => $terms]);

        $this->alertOwner();

        return $flag;
    }

    /**
     * At most one email per window, however many messages are flagged, so a spammer cannot flood the owner's
     * inbox or the SMTP account. Runs after the surrounding transaction commits and never throws: flagging and
     * alerting must not fail a send that was already delivered.
     */
    private function alertOwner(): void
    {
        if ((string) config('psst.moderation.alert_email') === '') {
            return;
        }

        DB::afterCommit(function () {
            $window = (int) config('psst.moderation.alert_window_minutes', 10);

            try {
                if (! Cache::add(self::ALERT_KEY, 1, now()->addMinutes($window))) {
                    return;
                }

                try {
                    NotifyAdminOfFlag::dispatch();
                } catch (Throwable $e) {
                    Cache::forget(self::ALERT_KEY);
                    throw $e;
                }
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
