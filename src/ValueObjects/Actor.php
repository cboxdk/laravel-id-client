<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Support\Claims;

/**
 * Who is REALLY at the keyboard when a token was minted for a support session — the
 * RFC 8693 `act` claim (`{"sub": "<staff subject>"}`).
 *
 * The token's own `sub` is the customer being helped; this is the member of staff acting
 * as them. Show it (a banner), record it (your audit log), and refuse what support must
 * never do on a customer's behalf (change their password, delete their data). Cbox ID
 * already caps these grants at 60 minutes with no refresh token.
 */
readonly class Actor
{
    /**
     * @param  array<string, mixed>  $claims  the whole `act` object, for nested actors
     */
    public function __construct(
        public string $subject,
        public array $claims = [],
    ) {}

    /** @param array<array-key, mixed> $claims */
    public static function fromClaims(array $claims): ?self
    {
        $act = Claims::object($claims, 'act');
        $subject = Claims::string($act, 'sub');

        return $subject !== null ? new self($subject, $act) : null;
    }
}
