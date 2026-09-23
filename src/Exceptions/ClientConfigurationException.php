<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The Cbox ID client is not configured.
 */
class ClientConfigurationException extends CboxIdException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
