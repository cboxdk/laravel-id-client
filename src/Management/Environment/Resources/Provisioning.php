<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `provisioning.*` on the environment plane. */
class Provisioning
{
    public readonly ProvisioningTargets $targets;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->targets = new ProvisioningTargets($transport);
    }
}
