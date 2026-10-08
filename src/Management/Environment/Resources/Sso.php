<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `sso.*` on the environment plane. */
class Sso
{
    public readonly SsoConnections $connections;

    public readonly SsoDomains $domains;

    public readonly SsoSamlMetadata $samlMetadata;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->connections = new SsoConnections($transport);
        $this->domains = new SsoDomains($transport);
        $this->samlMetadata = new SsoSamlMetadata($transport);
    }
}
