<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Contracts;

use Cbox\Id\Client\AppCatalog\AppCatalogContext;
use Cbox\Id\Client\AppCatalog\SessionAppCatalogContext;
use Illuminate\Http\Request;

/**
 * Whose catalog a request reads: the Cbox ID subject and organization. Bound to
 * {@see SessionAppCatalogContext}, which answers with the identity this session signed in
 * with. An app whose URL names the organization binds its own.
 *
 * Return null when the request has no person or no organization: the routes answer 403.
 */
interface ResolvesAppCatalogContext
{
    public function resolve(Request $request): ?AppCatalogContext;
}
