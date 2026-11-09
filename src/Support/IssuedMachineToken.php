<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

/**
 * A client-credentials token as the token endpoint returned it: the token and, when
 * the response carried one, its `expires_in` in seconds.
 */
readonly class IssuedMachineToken
{
    public function __construct(
        public string $token = '',
        public ?int $expiresIn = null,
    ) {}
}
