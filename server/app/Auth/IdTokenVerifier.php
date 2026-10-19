<?php

namespace App\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Verifies OpenID Connect ID tokens issued by Google or Apple.
 *
 * Fails closed: an unconfigured provider or empty audience list rejects everything.
 */
class IdTokenVerifier
{
    private const JWKS_TTL = 3600;

    private const LEEWAY_SECONDS = 30;

    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
    ) {}

    /**
     * @throws InvalidIdToken
     */
    public function verify(string $provider, string $idToken, string $nonce, ?string $nameHint = null): VerifiedIdentity
    {
        $config = config("services.oidc.$provider");

        if (! is_array($config) || empty($config['client_ids']) || empty($config['issuers'])) {
            throw new InvalidIdToken("provider [$provider] is not configured");
        }

        $claims = $this->decode($provider, $config['jwks_uri'], $idToken);

        if (! in_array($claims['iss'] ?? null, $config['issuers'], true)) {
            throw new InvalidIdToken('unexpected issuer');
        }

        $audiences = (array) ($claims['aud'] ?? []);
        if (array_intersect($audiences, $config['client_ids']) === []) {
            throw new InvalidIdToken('unexpected audience');
        }

        $this->assertNonce($claims['nonce'] ?? null, $nonce);

        $subject = $claims['sub'] ?? null;
        $email = $claims['email'] ?? null;

        if (! is_string($subject) || $subject === '' || ! is_string($email) || $email === '') {
            throw new InvalidIdToken('missing subject or email');
        }

        // Apple sends "true"/"false" strings; Google sends booleans.
        if (! filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw new InvalidIdToken('email is not verified');
        }

        return new VerifiedIdentity(
            provider: $provider,
            subject: $subject,
            email: Str::lower($email),
            name: $this->cleanName($claims['name'] ?? $nameHint) ?? Str::before($email, '@'),
            avatarUrl: $this->httpsUrl($claims['picture'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $provider, string $jwksUri, string $idToken): array
    {
        $previous = JWT::$leeway;
        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            $keys = JWK::parseKeySet($this->jwks($provider, $jwksUri, $this->kid($idToken)), 'RS256');

            return (array) JWT::decode($idToken, $keys);
        } catch (Throwable $e) {
            throw new InvalidIdToken('token rejected: '.$e::class, previous: $e);
        } finally {
            JWT::$leeway = $previous;
        }
    }

    private function kid(string $idToken): ?string
    {
        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $idToken)[0]), true);

        return is_array($header) && is_string($header['kid'] ?? null) ? $header['kid'] : null;
    }

    /**
     * Cached key set; refetched once when a token names a key we have not seen (key rotation).
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    private function jwks(string $provider, string $jwksUri, ?string $kid): array
    {
        $cacheKey = "oidc:jwks:$provider";
        $jwks = $this->cache->get($cacheKey);

        if (! is_array($jwks) || ($kid !== null && ! $this->hasKey($jwks, $kid))) {
            $jwks = $this->http->timeout(5)->retry(2, 200)->get($jwksUri)->throw()->json();
            $this->cache->put($cacheKey, $jwks, self::JWKS_TTL);
        }

        return $jwks;
    }

    /**
     * @param  array<string, mixed>  $jwks
     */
    private function hasKey(array $jwks, string $kid): bool
    {
        return in_array($kid, array_column($jwks['keys'] ?? [], 'kid'), true);
    }

    private function assertNonce(mixed $claim, string $nonce): void
    {
        // Native Apple flows send the SHA-256 of the nonce; Google and web flows send it as-is.
        if (! is_string($claim)
            || ! (hash_equals($nonce, $claim) || hash_equals(hash('sha256', $nonce), $claim))) {
            throw new InvalidIdToken('nonce mismatch');
        }
    }

    private function cleanName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $name = trim(strip_tags($name));

        return $name === '' ? null : Str::limit($name, 80, '');
    }

    private function httpsUrl(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') && strlen($url) <= 2048 ? $url : null;
    }
}
