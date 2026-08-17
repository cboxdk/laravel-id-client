<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use RuntimeException;

/**
 * A presented access token was not accepted.
 *
 * Separate from {@see AuthenticationFailed}, which is a LOGIN that did not complete.
 * This is a request that arrived with a credential the API will not honour, and the
 * two want different answers: a failed login is a redirect back to the identity
 * provider, a rejected token is a 401 with a reason.
 *
 * Conflating them is how a rejected token becomes a 500 — a caller then retries a
 * credential that will never be accepted, instead of fetching a new one.
 */
class TokenRejected extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
