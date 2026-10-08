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

/** `members.roles.*` on the environment plane. */
class MembersRoles
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Grant a member a role in the organization
     *
     * Idempotent.
     *
     * **This API grants with the environment's authority.** It is your backend, so it may
     * grant any role the organization can hold — including a **staff role**
     * (`tenant_assignable: false`), such as your support lead's "Support" role at one
     * customer. An organization's own administrators still cannot: their console, their
     * invitations and their directory mappings refuse staff roles, and nothing here
     * changes that.
     *
     * Refusals: `422 role_not_assignable` (another organization's role, or one from an app
     * this organization cannot use), `409 role_conflict` (segregation of duties).
     *
     * `PUT /organizations/{organization_id}/members/{user_id}/roles/{role_id}` · action `members.roles.grant` · scope `roles:write`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<RoleAssignment>|PendingApprovalResult<ApiResponse<RoleAssignment>> : ApiResponse<RoleAssignment>)
     */
    public function grant(string $organizationId, string $userId, string $roleId, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.roles.grant'), [$organizationId, $userId, $roleId], $query, $options, Value::dto(RoleAssignment::fromArray(...)));
    }

    /**
     * List the roles a member holds in the organization
     *
     * Roles granted AT this organization. Roles a person holds
     * everywhere in the environment (staff) are listed under
     * `/users/{id}/environment-roles`.
     *
     * `GET /organizations/{organization_id}/members/{user_id}/roles` · action `members.roles.list` · scope `roles:read`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<RoleAssignment>>|PendingApprovalResult<ApiResponse<list<RoleAssignment>>> : ApiResponse<list<RoleAssignment>>)
     */
    public function list(string $organizationId, string $userId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.roles.list'), [$organizationId, $userId], [], $options, Value::list(Value::dto(RoleAssignment::fromArray(...))));
    }

    /**
     * Take a role back from a member
     *
     * Idempotent — a role the member does not hold is a 204 too.
     *
     * `DELETE /organizations/{organization_id}/members/{user_id}/roles/{role_id}` · action `members.roles.revoke` · scope `roles:write`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $organizationId, string $userId, string $roleId, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('members.roles.revoke'), [$organizationId, $userId, $roleId], $query, $options, Value::none(...));
    }
}
