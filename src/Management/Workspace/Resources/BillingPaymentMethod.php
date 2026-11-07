<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;

/** `billing.payment_method.*` on the workspace plane. */
class BillingPaymentMethod
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Choose the workspace default payment card.
     *
     * Requires scope `billing:write` and a role that may `manage-billing`.
     *
     * `PATCH /workspace/billing/payment-methods/{id}/default` · action `billing.payment_method.default` · scope `billing:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function default(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('billing.payment_method.default'), [$id], [], $options, Value::none(...));
    }

    /**
     * Remove an unneeded payment card from this workspace.
     *
     * Requires scope `billing:write` and a role that may `manage-billing`.
     *
     * `DELETE /workspace/billing/payment-methods/{id}` · action `billing.payment_method.remove` · scope `billing:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('billing.payment_method.remove'), [$id], [], $options, Value::none(...));
    }
}
