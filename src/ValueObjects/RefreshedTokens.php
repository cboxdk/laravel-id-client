<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * The answer to a refresh-token grant.
 *
 * `refreshToken` is never null: Cbox ID rotates refresh tokens and treats a replay of a
 * rotated one as theft, revoking the whole family — so ALWAYS persist this value and
 * discard the one you presented. When the server does not rotate, the one you presented
 * is handed back (RFC 6749 §6), so storing this field is always right.
 *
 * `claims` are the id_token's, verified (signature, issuer, audience, expiry) — empty
 * when the response carried no id_token.
 */
readonly class RefreshedTokens
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public ?string $idToken = null,
        public int $expiresIn = 0,
        public ?string $scope = null,
        public array $claims = [],
    ) {}
}
