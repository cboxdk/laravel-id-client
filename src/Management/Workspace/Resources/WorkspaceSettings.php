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

/** `workspace.settings.*` on the workspace plane. */
class WorkspaceSettings
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Rename the workspace.
     *
     * Requires scope `settings:write` and a role that may `manage-members`.
     *
     * `PATCH /workspace` · action `workspace.settings.update` · scope `settings:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function update(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('workspace.settings.update'), [], $body, $options, Value::dto(Organization::fromArray(...)));
    }
}
