<?php

namespace App\Auth;

/**
 * Claims taken from an ID token whose signature, issuer, audience, expiry and nonce were all verified.
 */
final readonly class VerifiedIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public string $email,
        public ?string $name,
        public ?string $avatarUrl,
    ) {}
}
