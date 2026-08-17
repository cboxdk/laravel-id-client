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
 *
 * **Two rejections, and they are not interchangeable.** A token that failed
 * verification is not the same event as a genuine token that lacks a scope: the first
 * is answered by fetching a new token, the second only by being granted more, and
 * OAuth gives them different names and different statuses for that reason. Which one
 * happened is carried here, on the exception, because it is a fact about the token —
 * inferring it from what the ROUTE happened to require gets it wrong every time a
 * scoped route is handed a forged token.
 */
class TokenRejected extends RuntimeException
{
    /**
     * @param  list<string>  $missingScopes  empty when verification itself failed
     */
    private function __construct(string $message, public readonly array $missingScopes = [])
    {
        parent::__construct($message);
    }

    /**
     * Verification failed: signature, issuer, audience, expiry, or shape.
     */
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * The token is genuine; the caller was not granted enough.
     *
     * @param  list<string>  $missing
     */
    public static function missingScopes(array $missing): self
    {
        return new self('The token is missing required scope(s): '.implode(', ', $missing), $missing);
    }

    /**
     * True when the token itself is fine and only the grant fell short.
     */
    public function isInsufficientScope(): bool
    {
        return $this->missingScopes !== [];
    }
}
