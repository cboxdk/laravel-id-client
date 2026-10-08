<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Resources;

use Cbox\Id\Client\Management\Platform\Operations;
use Cbox\Id\Client\Management\Platform\Schemas\PlatformOrganization;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `organizations.*` on the platform plane. */
class Organizations
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create an organization inside an environment, optionally under a parent organization of the same environment.
     *
     * Requires scope `operator:organizations:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `POST /platform/environments/{environment_id}/organizations` · action `platform.organizations.create` · scope `operator:organizations:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, type: 'customer'|'reseller', parent_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformOrganization>|PendingApprovalResult<ApiResponse<PlatformOrganization>> : ApiResponse<PlatformOrganization>)
     */
    public function create(string $environmentId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.organizations.create'), [$environmentId], $body, $options, Value::dto(PlatformOrganization::fromArray(...)));
    }

    /**
     * Move an organization under another organization of the same environment, or to the top of its hierarchy.
     *
     * Requires scope `operator:organizations:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `PUT /platform/environments/{environment_id}/organizations/{organization_id}/parent` · action `platform.organizations.move` · scope `operator:organizations:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{parent_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformOrganization>|PendingApprovalResult<ApiResponse<PlatformOrganization>> : ApiResponse<PlatformOrganization>)
     */
    public function move(string $environmentId, string $organizationId, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.organizations.move'), [$environmentId, $organizationId], $body, $options, Value::dto(PlatformOrganization::fromArray(...)));
    }

    /**
     * Suspend an organization inside an environment (its members can no longer sign in to it) or reactivate it.
     *
     * Requires scope `operator:organizations:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `PUT /platform/environments/{environment_id}/organizations/{organization_id}/status` · action `platform.organizations.set_status` · scope `operator:organizations:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{status: 'active'|'suspended'}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformOrganization>|PendingApprovalResult<ApiResponse<PlatformOrganization>> : ApiResponse<PlatformOrganization>)
     */
    public function setStatus(string $environmentId, string $organizationId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.organizations.set_status'), [$environmentId, $organizationId], $body, $options, Value::dto(PlatformOrganization::fromArray(...)));
    }
}
