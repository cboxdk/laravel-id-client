<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * Cbox ID answered 429 `slow_down` when a request was started: too many requests for
 * this person or this client. Start a new one no sooner than `$retryAfter` seconds.
 */
class GrantRateLimited extends GrantFailed
{
    public function __construct(
        string $message,
        public readonly int $retryAfter,
        ?string $description = null,
    ) {
        parent::__construct($message, 'slow_down', $description, 429);
    }
}
