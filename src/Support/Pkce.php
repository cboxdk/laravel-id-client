<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

/**
 * PKCE (RFC 7636, S256). The verifier is a high-entropy secret kept in the session;
 * only its SHA-256 challenge travels on the authorize URL, so an intercepted
 * authorization code cannot be redeemed without the verifier.
 */
class Pkce
{
    public static function verifier(): string
    {
        return self::base64Url(random_bytes(48));
    }

    public static function challenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
