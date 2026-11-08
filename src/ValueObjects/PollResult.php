<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * One poll of the token endpoint.
 *
 * Either the grant is done (`tokens` is set) or it is still pending, and `interval` is
 * how many seconds to wait before the next poll — already raised by five when Cbox ID
 * answered `slow_down` (RFC 8628 §3.5). `new PollResult` is a plain pending answer.
 */
readonly class PollResult
{
    public function __construct(
        public ?TokenResult $tokens = null,
        public int $interval = 5,
        public bool $slowDown = false,
    ) {}

    public function granted(): bool
    {
        return $this->tokens !== null;
    }
}
