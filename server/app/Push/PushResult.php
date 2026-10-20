<?php

namespace App\Push;

enum PushResult
{
    case Delivered;
    /** The push service says the subscription no longer exists (404/410): delete it. */
    case Gone;
    /** Temporary trouble (network, 429, 5xx): try again later. */
    case Retry;
    /** Permanent rejection: give up and log. */
    case Failed;
}
