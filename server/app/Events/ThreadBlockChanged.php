<?php

namespace App\Events;

class ThreadBlockChanged extends UserChannelEvent
{
    public function broadcastAs(): string
    {
        return 'thread.block-changed';
    }
}
