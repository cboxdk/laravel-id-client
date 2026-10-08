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
use Cbox\Id\Client\Management\Workspace\Schemas\Project;

/** `projects.*` on the workspace plane. */
class Projects
{
    public readonly ProjectsVerification $verification;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->verification = new ProjectsVerification($transport);
    }

    /**
     * Create a project
     *
     * Stand up another independently-billed IdP product. Requires scope `projects:write` and the `manage-environments` capability (owner/admin/developer).
     *
     * `POST /workspace/projects` · action `projects.create` · scope `projects:write`
     *
     * @param  array{name: string, environment_limit?: int}  $body
     * @return ApiResponse<Project>
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('projects.create'), [], $body, $options, Value::dto(Project::fromArray(...)));
    }

    /**
     * List projects
     *
     * Requires scope `workspace:read` (any role). The organization's projects (IdP products). Each carries its own plan and environment allowance.
     *
     * `GET /workspace/projects` · action `projects.list` · scope `workspace:read`
     *
     * @return ApiResponse<list<Project>>
     */
    public function list(?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('projects.list'), [], [], $options, Value::list(Value::dto(Project::fromArray(...))));
    }

    /**
     * Reactivate a suspended project, so environments can be added to it again.
     *
     * Requires scope `projects:write` and a role that may `manage-environments`.
     *
     * `POST /workspace/projects/{id}/reactivate` · action `projects.reactivate` · scope `projects:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Project>|PendingApprovalResult<ApiResponse<Project>> : ApiResponse<Project>)
     */
    public function reactivate(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('projects.reactivate'), [$id], [], $options, Value::dto(Project::fromArray(...)));
    }

    /**
     * Rename a project.
     *
     * Requires scope `projects:write` and a role that may `manage-environments`.
     *
     * `PATCH /workspace/projects/{id}` · action `projects.rename` · scope `projects:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Project>|PendingApprovalResult<ApiResponse<Project>> : ApiResponse<Project>)
     */
    public function rename(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('projects.rename'), [$id], $body, $options, Value::dto(Project::fromArray(...)));
    }

    /**
     * Suspend a project: its environments stay live, but no new ones can be added until it is reactivated.
     *
     * Requires scope `projects:write` and a role that may `manage-environments`.
     *
     * `POST /workspace/projects/{id}/suspend` · action `projects.suspend` · scope `projects:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Project>|PendingApprovalResult<ApiResponse<Project>> : ApiResponse<Project>)
     */
    public function suspend(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('projects.suspend'), [$id], [], $options, Value::dto(Project::fromArray(...)));
    }
}
