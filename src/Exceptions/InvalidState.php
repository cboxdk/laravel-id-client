<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The login state did not match — the request may be forged or stale.
 */
class InvalidState extends CboxIdException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
