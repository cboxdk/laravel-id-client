<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Cbox\Id\Client\Contracts\ResolvesAppCatalogContext;
use Cbox\Id\Client\Tenancy\SessionIdentityStore;
use Illuminate\Http\Request;

/**
 * The identity this session signed in with, while the same local user is logged in. A
 * support session (`act`) gets no catalog: the grant would be the customer's.
 */
final readonly class SessionAppCatalogContext implements ResolvesAppCatalogContext
{
    public function __construct(private SessionIdentityStore $sessions) {}

    public function resolve(Request $request): ?AppCatalogContext
    {
        $user = $request->user();
        $identity = $user !== null ? $this->sessions->current($user) : null;
        $organization = $identity?->claims['org'] ?? null;

        if ($identity === null || $identity->subject === '' || ! is_string($organization) || $organization === ''
            || array_key_exists('act', $identity->claims)) {
            return null;
        }

        return new AppCatalogContext($identity->subject, $organization);
    }
}
