<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use RuntimeException;

/**
 * The Cbox ID client is not configured.
 */
final class ClientConfigurationException extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
