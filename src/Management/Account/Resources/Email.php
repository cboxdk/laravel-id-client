<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `email.*` on the account plane. */
class Email
{
    public readonly EmailConfirmation $confirmation;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->confirmation = new EmailConfirmation($transport);
    }
}
