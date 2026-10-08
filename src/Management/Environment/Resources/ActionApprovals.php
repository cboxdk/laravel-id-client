<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\ActionApprovalsGetResult;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Value;

/** `action_approvals.*` on the environment plane. */
class ActionApprovals
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Where an approval this key asked for stands
     *
     * Any key may poll the approvals it raised, and only those: another key's id is a 404.
     * `status` is `pending`, `approved`, `denied`, `expired` or `consumed`.
     *
     * `GET /action-approvals/{id}`
     *
     * @return ApiResponse<ActionApprovalsGetResult>
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse
    {
        return $this->transport->callAndWait(Operations::spec('action_approvals.get'), [$id], [], $options, Value::dto(ActionApprovalsGetResult::fromArray(...)));
    }
}
