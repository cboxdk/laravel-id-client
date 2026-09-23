<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use RuntimeException;

/**
 * One RSA signing key per test process — generating one costs tens of milliseconds, and
 * a suite that fakes Cbox ID in every test should not pay it every time.
 *
 * Test-only by construction: the private key never leaves this process, and nothing but
 * {@see FakeDiscovery} publishes the public half.
 *
 * @internal
 */
final class TestKeys
{
    public const KID = 'cbox-id-fake';

    /** @var array{private: string, jwks: array<string, mixed>}|null */
    private static ?array $pair = null;

    /**
     * @return array{private: string, jwks: array<string, mixed>}
     */
    public static function pair(): array
    {
        if (self::$pair !== null) {
            return self::$pair;
        }

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

        if ($resource === false || ! openssl_pkey_export($resource, $private) || ! is_string($private)) {
            throw new RuntimeException('Could not generate a test signing key (is the openssl extension loaded?).');
        }

        $details = openssl_pkey_get_details($resource);
        $rsa = is_array($details) && is_array($details['rsa'] ?? null) ? $details['rsa'] : [];
        $n = $rsa['n'] ?? null;
        $e = $rsa['e'] ?? null;

        if (! is_string($n) || ! is_string($e)) {
            throw new RuntimeException('Could not read the test signing key.');
        }

        $b64 = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return self::$pair = [
            'private' => $private,
            'jwks' => ['keys' => [[
                'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => self::KID,
                'n' => $b64($n), 'e' => $b64($e),
            ]]],
        ];
    }
}
