<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `token_vault.*` on the environment plane. */
class TokenVault
{
    public readonly TokenVaultGrants $grants;

    public readonly TokenVaultSecrets $secrets;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->grants = new TokenVaultGrants($transport);
        $this->secrets = new TokenVaultSecrets($transport);
    }
}
