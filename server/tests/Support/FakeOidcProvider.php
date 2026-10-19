<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

/**
 * Stands in for Google/Apple: owns an RSA key, publishes its JWKS via Http::fake and signs ID tokens.
 */
class FakeOidcProvider
{
    public const NONCE = 'a-sufficiently-long-nonce-123';

    private OpenSSLAsymmetricKey $key;

    public function __construct(
        public readonly string $provider = 'google',
        public readonly string $clientId = 'test-client-id',
        public readonly string $kid = 'test-key-1',
    ) {
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        config([
            "services.oidc.{$provider}.client_ids" => [$clientId],
        ]);

        Http::fake([
            config("services.oidc.{$provider}.jwks_uri") => Http::response($this->jwks()),
        ]);
    }

    /**
     * @return array{keys: list<array<string, string>>}
     */
    public function jwks(): array
    {
        $details = openssl_pkey_get_details($this->key);

        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => $this->kid,
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
            'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
        ]]];
    }

    /**
     * @param  array<string, mixed>  $overrides  Claims to change; a null value removes the claim.
     */
    public function token(array $overrides = [], ?OpenSSLAsymmetricKey $signWith = null): string
    {
        $issuer = config("services.oidc.{$this->provider}.issuers")[0];

        $claims = array_filter(array_merge([
            'iss' => $issuer,
            'aud' => $this->clientId,
            'sub' => 'provider-subject-1',
            'email' => 'sam@example.com',
            'email_verified' => true,
            'name' => 'Sam Example',
            'picture' => 'https://example.com/sam.png',
            'nonce' => self::NONCE,
            'iat' => time() - 10,
            'exp' => time() + 300,
        ], $overrides), fn ($value) => $value !== null);

        $pem = '';
        openssl_pkey_export($signWith ?? $this->key, $pem);

        return JWT::encode($claims, $pem, 'RS256', $this->kid);
    }

    public static function strangerKey(): OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }
}
