<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `authenticator.*` on the environment plane. */
class Authenticator
{
    public readonly AuthenticatorMe $me;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->me = new AuthenticatorMe($transport);
    }
}
