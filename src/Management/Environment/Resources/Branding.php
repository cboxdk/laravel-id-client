<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `branding.*` on the environment plane. */
class Branding
{
    public readonly BrandingAppearance $appearance;

    public readonly BrandingWhitelabel $whitelabel;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->appearance = new BrandingAppearance($transport);
        $this->whitelabel = new BrandingWhitelabel($transport);
    }
}
