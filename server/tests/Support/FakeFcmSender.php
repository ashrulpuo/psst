<?php

namespace Tests\Support;

use App\Models\PushSubscription;
use App\Push\FcmSender;
use App\Push\PushResult;

class FakeFcmSender implements FcmSender
{
    /** @var list<array{subscription: PushSubscription, payload: array<string, mixed>}> */
    public array $sent = [];

    public PushResult $result = PushResult::Delivered;

    public function send(PushSubscription $subscription, array $payload, string $topic): PushResult
    {
        $this->sent[] = compact('subscription', 'payload');

        return $this->result;
    }
}
