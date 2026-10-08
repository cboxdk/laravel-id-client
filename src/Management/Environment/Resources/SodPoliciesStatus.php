<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SodPolicy;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `sod_policies.status.*` on the environment plane. */
class SodPoliciesStatus
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Enforce a role-conflict rule, or stop enforcing it — switched off, grants that break it are allowed again.
     *
     * `POST /sod-policies/{id}/status` · action `sod_policies.status.set` · scope `governance:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{active: bool, organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SodPolicy>|PendingApprovalResult<ApiResponse<SodPolicy>> : ApiResponse<SodPolicy>)
     */
    public function set(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sod_policies.status.set'), [$id], $body, $options, Value::dto(SodPolicy::fromArray(...)));
    }
}
