<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Resources;

use Cbox\Id\Client\Management\Platform\Operations;
use Cbox\Id\Client\Management\Platform\Schemas\PlatformOrganization;
use Cbox\Id\Client\Management\Platform\Schemas\ProvisionedWorkspace;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `workspaces.*` on the platform plane. */
class Workspaces
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create a workspace with its owner, first project and first environment; the owner is emailed a link to set their password.
     *
     * Requires scope `operator:workspaces:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `POST /platform/workspaces` · action `platform.workspaces.create` · scope `operator:workspaces:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, owner_email: string, owner_name: string, environment_limit: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<ProvisionedWorkspace>|PendingApprovalResult<ApiResponse<ProvisionedWorkspace>> : ApiResponse<ProvisionedWorkspace>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.workspaces.create'), [], $body, $options, Value::dto(ProvisionedWorkspace::fromArray(...)));
    }

    /**
     * Suspend a workspace (its people can no longer sign in, its environments stop serving) or reactivate it.
     *
     * Requires scope `operator:workspaces:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `PUT /platform/workspaces/{workspace_id}/status` · action `platform.workspaces.set_status` · scope `operator:workspaces:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{status: 'active'|'suspended'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformOrganization>|PendingApprovalResult<ApiResponse<PlatformOrganization>> : ApiResponse<PlatformOrganization>)
     */
    public function setStatus(string $workspaceId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.workspaces.set_status'), [$workspaceId], $body, $options, Value::dto(PlatformOrganization::fromArray(...)));
    }
}
