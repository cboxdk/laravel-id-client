<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Facades;

use Cbox\Id\Client\Contracts\Management;
use Illuminate\Support\Facades\Facade;

/**
 * The environment management API. Needs `CBOX_ID_MANAGEMENT_KEY` (a `cbid_env_…` key).
 *
 *     CboxIdManagement::createOrganization(new NewOrganization('Acme', ownerUserId: $user->cbox_id));
 *     CboxIdManagement::invite($orgId, new NewInvitation('ada@example.com', roles: ['editor']));
 *
 * @see Management for every method and the scope it needs
 */
class CboxIdManagement extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Management::class;
    }
}
