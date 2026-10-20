<?php

namespace App\Events;

class MessagePosted extends UserChannelEvent
{
    public function broadcastAs(): string
    {
        return 'message.posted';
    }
}
