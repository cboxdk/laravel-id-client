<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * A back-channel logout token failed validation (OIDC Back-Channel Logout 1.0 §2.6).
 *
 * The receiver answers it with a 400 `invalid_request`, which Cbox ID records as a final
 * failure — the message is that reason, and is what ends up in the environment's audit
 * trail, so it names the check that failed and never echoes the token.
 */
class LogoutTokenRejected extends CboxIdException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
