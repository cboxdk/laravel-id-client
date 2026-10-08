<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `organizations.*` on the account plane. */
class Organizations
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Leave one of your own organizations. The last owner cannot leave — transfer ownership first.
     *
     * Requires scope `account:organizations:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `POST /me/organizations/{organization_id}/leave` · action `account.organizations.leave` · scope `account:organizations:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function leave(string $organizationId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.organizations.leave'), [$organizationId], [], $options, Value::none(...));
    }
}
