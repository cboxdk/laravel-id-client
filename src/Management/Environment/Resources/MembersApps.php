<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `members.apps.*` on the environment plane. */
class MembersApps
{
    public readonly MembersAppsRoles $roles;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->roles = new MembersAppsRoles($transport);
    }
}
