<?php

namespace App\Events;

class MessagesRead extends UserChannelEvent
{
    public function broadcastAs(): string
    {
        return 'messages.read';
    }
}
