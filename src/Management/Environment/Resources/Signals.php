<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `signals.*` on the environment plane. */
class Signals
{
    public readonly SignalsSource $source;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->source = new SignalsSource($transport);
    }
}
