<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Resources;

use Cbox\Id\Client\Management\Platform\Operations;
use Cbox\Id\Client\Management\Platform\Schemas\PlatformOperator;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `operators.*` on the platform plane. */
class Operators
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Add a platform operator: a person with authority over the whole deployment.
     *
     * Requires scope `operator:operators:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `POST /platform/operators` · action `platform.operators.create` · scope `operator:operators:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, email: string, password: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformOperator>|PendingApprovalResult<ApiResponse<PlatformOperator>> : ApiResponse<PlatformOperator>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.operators.create'), [], $body, $options, Value::dto(PlatformOperator::fromArray(...)));
    }

    /**
     * Suspend a platform operator, or reactivate one. Never yourself, and never the last active operator.
     *
     * Requires scope `operator:operators:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `PUT /platform/operators/{operator_id}/status` · action `platform.operators.set_status` · scope `operator:operators:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{status: 'active'|'suspended'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformOperator>|PendingApprovalResult<ApiResponse<PlatformOperator>> : ApiResponse<PlatformOperator>)
     */
    public function setStatus(string $operatorId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.operators.set_status'), [$operatorId], $body, $options, Value::dto(PlatformOperator::fromArray(...)));
    }
}
