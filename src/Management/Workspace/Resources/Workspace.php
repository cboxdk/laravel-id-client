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
use Cbox\Id\Client\Management\Workspace\Schemas\Organization;

/** `workspace.*` on the workspace plane. */
class Workspace
{
    public readonly WorkspaceSettings $settings;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->settings = new WorkspaceSettings($transport);
    }

    /**
     * Get the workspace
     *
     * Requires scope `workspace:read` and current workspace access. Returns only the workspace identity and status, including for members with only the Billing app role.
     *
     * `GET /workspace` · action `workspace.get` · scope `workspace:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function get(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('workspace.get'), [], [], $options, Value::dto(Organization::fromArray(...)));
    }
}
