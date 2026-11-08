<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * The tokens a CIBA or device grant ended with (RFC 6749 §5.1).
 *
 * The `idToken` is passed through as Cbox ID returned it and is NOT verified here.
 * Treat it as opaque, or verify it before reading claims from it. The access token is
 * a credential: never log it or send it to a browser.
 */
readonly class TokenResult
{
    public function __construct(
        #[\SensitiveParameter] public string $accessToken = '',
        public string $tokenType = 'Bearer',
        public ?int $expiresIn = null,
        public ?string $scope = null,
        #[\SensitiveParameter] public ?string $refreshToken = null,
        public ?string $idToken = null,
    ) {}

    /** @return list<string> The granted scopes, or an empty list when none were echoed. */
    public function scopes(): array
    {
        return $this->scope === null ? [] : array_values(array_filter(explode(' ', $this->scope), static fn (string $s): bool => $s !== ''));
    }
}
