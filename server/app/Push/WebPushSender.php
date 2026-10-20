<?php

namespace App\Push;

use App\Models\PushSubscription;

interface WebPushSender
{
    /**
     * @param  array<string, mixed>  $payload  JSON-encoded and end-to-end encrypted to the subscription's keys.
     * @param  string  $topic  Lets the push service replace an undelivered earlier message of the same topic.
     */
    public function send(PushSubscription $subscription, array $payload, string $topic): PushResult;
}
