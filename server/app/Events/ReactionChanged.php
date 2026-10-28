<?php

namespace App\Events;

/** A message's reaction tally changed. Both participants get their own `mine` flags. */
class ReactionChanged extends UserChannelEvent
{
    public function broadcastAs(): string
    {
        return 'thread.reaction-changed';
    }
}
