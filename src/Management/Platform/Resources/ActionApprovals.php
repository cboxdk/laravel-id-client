<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Resources;

use Cbox\Id\Client\Management\Platform\Operations;
use Cbox\Id\Client\Management\Platform\Schemas\ActionApprovalsGetResult;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Value;

/** `action_approvals.*` on the platform plane. */
class ActionApprovals
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Where an approval you asked for stands
     *
     * The `poll_url` of a `202 approval_required`: an approval this token raised, and only those — another token's id is a 404. `status` is `pending`, `approved`, `denied`, `expired` or `consumed`.
     *
     * `GET /platform/action-approvals/{id}`
     *
     * @return ApiResponse<ActionApprovalsGetResult>
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('action_approvals.get'), [$id], [], $options, Value::dto(ActionApprovalsGetResult::fromArray(...)));
    }
}
