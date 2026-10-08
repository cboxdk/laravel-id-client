<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `keys.*` on the workspace plane. */
class Keys
{
    public readonly KeysEnvironment $environment;

    public readonly KeysWorkspace $workspace;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->environment = new KeysEnvironment($transport);
        $this->workspace = new KeysWorkspace($transport);
    }
}
