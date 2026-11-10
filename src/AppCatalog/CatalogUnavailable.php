<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Exceptions\CboxIdException;

/**
 * Cbox ID could not be reached, or answered with something unusable. The same grant or
 * refresh token is still good in a moment: retry, do not sign in again.
 */
final class CatalogUnavailable extends CboxIdException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
