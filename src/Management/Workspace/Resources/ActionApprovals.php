<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;
use Cbox\Id\Client\Management\Workspace\Schemas\ActionApprovalsGetResult;

/** `action_approvals.*` on the workspace plane. */
class ActionApprovals
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Where an approval this key asked for stands
     *
     * Any workspace key — or a member's token — may poll the approvals it raised, and only
     * those: another credential's id is a 404. `status` is `pending`, `approved`, `denied`, `expired` or `consumed`.
     *
     * `GET /workspace/action-approvals/{id}`
     *
     * @return ApiResponse<ActionApprovalsGetResult>
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('action_approvals.get'), [$id], [], $options, Value::dto(ActionApprovalsGetResult::fromArray(...)));
    }
}
