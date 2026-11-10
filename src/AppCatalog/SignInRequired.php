<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Exceptions\CboxIdException;

/**
 * The catalog grant cannot be renewed on the server: there is no refresh token, Cbox ID
 * refused it, or it belongs to another organization. Only a sign-in gets a new one.
 */
final class SignInRequired extends CboxIdException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
