<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
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
     * Get the organization
     *
     * Requires scope `workspace:read` (any role). Returns the organization's identity. The `projects` block (each project's plan/allowance) is included only for keys whose role can read billing (owner/admin/viewer — not developer).
     *
     * `GET /workspace` · action `workspace.get` · scope `workspace:read`
     *
     * @return ApiResponse<Organization>
     */
    public function get(?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('workspace.get'), [], [], $options, Value::dto(Organization::fromArray(...)));
    }
}
