<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `mfa.*` on the account plane. */
class Mfa
{
    public readonly MfaSms $sms;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->sms = new MfaSms($transport);
    }
}
