<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Exceptions\CboxIdException;

/**
 * Cbox ID will not let this app read catalogs with its own token: the client has not
 * been given `app_catalog:resolve` yet. The catalog falls back to the person's grant.
 *
 * @internal
 */
final class MachineCatalogRefused extends CboxIdException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
