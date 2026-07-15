<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use RuntimeException;

/**
 * Login could not be completed.
 */
final class AuthenticationFailed extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
