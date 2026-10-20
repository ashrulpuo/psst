<?php

namespace App\Push;

use App\Models\PushSubscription;

interface FcmSender
{
    /**
     * @param  array<string, mixed>  $payload  Same shape as the Web Push payload (see PushPayload).
     * @param  string  $topic  Unused by FCM (collapsing uses the payload tag); kept so both senders share a shape.
     */
    public function send(PushSubscription $subscription, array $payload, string $topic): PushResult;
}
