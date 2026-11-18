<?php

namespace App\Moderation;

use App\Jobs\NotifyAdminOfFlag;
use App\Models\Message;
use App\Models\MessageFlag;

class FlagInbound
{
    public function __construct(private readonly KeywordFlagger $flagger) {}

    /** Record a keyword hit. The message is delivered regardless; this only leaves a note for review. */
    public function handle(Message $message, string $body): ?MessageFlag
    {
        $terms = $this->flagger->matches($body);

        if ($terms === []) {
            return null;
        }

        $flag = MessageFlag::create(['message_id' => $message->id, 'terms' => $terms]);

        // Queued after the surrounding transaction commits, so a mail problem can never roll back the flag.
        NotifyAdminOfFlag::dispatch();

        return $flag;
    }
}
