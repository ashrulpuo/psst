<?php

namespace Tests\Support;

use App\Models\PushSubscription;
use App\Push\PushResult;
use App\Push\WebPushSender;

/**
 * Records what would have been sent so tests never touch the network.
 */
class FakeWebPushSender implements WebPushSender
{
    /** @var list<array{subscription: PushSubscription, payload: array<string, mixed>, topic: string}> */
    public array $sent = [];

    public PushResult $result = PushResult::Delivered;

    public function send(PushSubscription $subscription, array $payload, string $topic): PushResult
    {
        $this->sent[] = compact('subscription', 'payload', 'topic');

        return $this->result;
    }
}
