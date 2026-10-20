<?php

namespace App\Messaging;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A rule of the messaging domain was violated. Renders as a JSON error with the given status.
 */
class CannotMessage extends HttpException
{
    public static function yourself(): self
    {
        return new self(422, 'You cannot send a message to yourself.');
    }

    public static function blocked(): self
    {
        return new self(403, 'This person is not accepting messages from you.');
    }

    public static function waitForReply(int $limit): self
    {
        return new self(429, "You have sent $limit messages without a reply. Wait for a response before sending more.");
    }

    public static function senderNotSignedIn(): self
    {
        return new self(409, 'You can reply once this person signs in to chat.');
    }
}
