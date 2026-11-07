<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\RoleAssignment;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `members.apps.roles.*` on the environment plane. */
class MembersAppsRoles
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Replace one app's member roles atomically. Other apps and the organization membership remain unchanged. An empty role list removes this app's access only.
     *
     * `PUT /organizations/{organization_id}/members/{user_id}/apps/{client_id}/roles` · action `members.apps.roles.replace` · scope `roles:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{version: string, roles: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<RoleAssignment>>|PendingApprovalResult<ApiResponse<list<RoleAssignment>>> : ApiResponse<list<RoleAssignment>>)
     */
    public function replace(string $organizationId, string $userId, string $clientId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.apps.roles.replace'), [$organizationId, $userId, $clientId], $body, $options, Value::list(Value::dto(RoleAssignment::fromArray(...))));
    }
}
