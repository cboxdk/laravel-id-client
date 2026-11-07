<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ManagementTransport;

/** `billing.*` on the workspace plane. */
class Billing
{
    public readonly BillingAccount $account;

    public readonly BillingPaymentMethod $paymentMethod;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->account = new BillingAccount($transport);
        $this->paymentMethod = new BillingPaymentMethod($transport);
    }
}
