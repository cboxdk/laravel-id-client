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
use Cbox\Id\Client\Management\Workspace\Schemas\CustomDomain;

/** `environments.domain.*` on the workspace plane. */
class EnvironmentsDomain
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Stop serving one of the workspace's environments on its custom domain. Anything using that domain stops reaching it.
     *
     * Requires scope `environments:write` and a role that may `manage-environments`.
     *
     * `DELETE /workspace/environments/{environment_id}/domain` · action `environments.domain.remove` · scope `environments:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $environmentId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('environments.domain.remove'), [$environmentId], [], $options, Value::none(...));
    }

    /**
     * Start serving one of the workspace's environments on a custom domain: returns the DNS TXT record that proves you control it.
     *
     * Requires scope `environments:write` and a role that may `manage-environments`.
     *
     * `POST /workspace/environments/{environment_id}/domain` · action `environments.domain.request` · scope `environments:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{domain: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<CustomDomain>|PendingApprovalResult<ApiResponse<CustomDomain>> : ApiResponse<CustomDomain>)
     */
    public function request(string $environmentId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('environments.domain.request'), [$environmentId], $body, $options, Value::dto(CustomDomain::fromArray(...)));
    }

    /**
     * Look for the pending domain's DNS TXT record and, once it is visible, serve the environment on that domain.
     *
     * Requires scope `environments:write` and a role that may `manage-environments`.
     *
     * `POST /workspace/environments/{environment_id}/domain/verify` · action `environments.domain.verify` · scope `environments:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<CustomDomain>|PendingApprovalResult<ApiResponse<CustomDomain>> : ApiResponse<CustomDomain>)
     */
    public function verify(string $environmentId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('environments.domain.verify'), [$environmentId], [], $options, Value::dto(CustomDomain::fromArray(...)));
    }
}
