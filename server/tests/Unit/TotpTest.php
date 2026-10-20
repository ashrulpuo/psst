<?php

namespace Tests\Unit;

use App\Admin\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 Appendix B secret: ASCII "12345678901234567890". */
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * The RFC lists 8-digit codes; the 6-digit code is the last six digits of the same value.
     *
     * @return array<string, array{0: int, 1: string}>
     */
    public static function rfcVectors(): array
    {
        return [
            't=59' => [59, '287082'],
            't=1111111109' => [1111111109, '081804'],
            't=1111111111' => [1111111111, '050471'],
            't=1234567890' => [1234567890, '005924'],
            't=2000000000' => [2000000000, '279037'],
            't=20000000000' => [20000000000, '353130'],
        ];
    }

    #[DataProvider('rfcVectors')]
    public function test_it_matches_the_rfc_6238_test_vectors(int $time, string $expected): void
    {
        $this->assertSame($expected, Totp::code(self::SECRET, intdiv($time, 30)));
    }

    public function test_verify_returns_the_matching_step_within_one_step_of_drift(): void
    {
        $now = 1111111111;
        $step = intdiv($now, 30);

        $this->assertSame($step, Totp::verify(self::SECRET, Totp::code(self::SECRET, $step), $now));
        $this->assertSame($step - 1, Totp::verify(self::SECRET, Totp::code(self::SECRET, $step - 1), $now));
        $this->assertSame($step + 1, Totp::verify(self::SECRET, Totp::code(self::SECRET, $step + 1), $now));
    }

    public function test_verify_rejects_codes_two_steps_away_and_malformed_input(): void
    {
        $now = 1111111111;
        $step = intdiv($now, 30);

        $this->assertNull(Totp::verify(self::SECRET, Totp::code(self::SECRET, $step + 2), $now));
        $this->assertNull(Totp::verify(self::SECRET, Totp::code(self::SECRET, $step - 2), $now));
        $this->assertNull(Totp::verify(self::SECRET, '12345', $now));
        $this->assertNull(Totp::verify(self::SECRET, 'abcdef', $now));
        $this->assertNull(Totp::verify(self::SECRET, '', $now));
    }

    public function test_base32_round_trips_and_generated_secrets_are_160_bits(): void
    {
        $bytes = random_bytes(20);
        $this->assertSame($bytes, Totp::base32Decode(Totp::base32Encode($bytes)));

        $secret = Totp::generateSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertSame(20, strlen(Totp::base32Decode($secret)));
        $this->assertNotSame($secret, Totp::generateSecret());
    }

    public function test_the_provisioning_uri_carries_issuer_account_and_secret(): void
    {
        $uri = Totp::uri('admin@example.test', self::SECRET, 'Psst');

        $this->assertStringStartsWith('otpauth://totp/Psst%3Aadmin%40example.test?', $uri);
        $this->assertStringContainsString('secret='.self::SECRET, $uri);
        $this->assertStringContainsString('issuer=Psst', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    public function test_an_invalid_secret_is_an_error_not_a_silent_pass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Totp::base32Decode('not base32 !!');
    }
}
