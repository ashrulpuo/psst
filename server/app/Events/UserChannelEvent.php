<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base for everything we push to one user's private channel (`users.{id}`).
 *
 * Payloads are built per recipient, so what one participant receives never contains what only the
 * other may know (a receiver never gets sender identity).
 */
abstract class UserChannelEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /** Queue the broadcast only once the surrounding transaction has committed. */
    public bool $afterCommit = true;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $recipientId,
        public readonly array $payload,
    ) {}

    abstract public function broadcastAs(): string;

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->recipientId}")];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
