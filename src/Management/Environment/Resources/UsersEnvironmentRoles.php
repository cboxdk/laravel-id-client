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

/** `users.environment_roles.*` on the environment plane. */
class UsersEnvironmentRoles
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Whether a user holds a role everywhere
     *
     * `200` with the grant, or `404` when it is not held.
     *
     * `GET /users/{id}/environment-roles/{role_id}` · action `users.environment_roles.get` · scope `roles:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<RoleAssignment>|PendingApprovalResult<ApiResponse<RoleAssignment>> : ApiResponse<RoleAssignment>)
     */
    public function get(string $id, string $roleId, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.environment_roles.get'), [$id, $roleId], $query, $options, Value::dto(RoleAssignment::fromArray(...)));
    }

    /**
     * Grant a role everywhere in the environment (staff)
     *
     * Idempotent. For your own people who act across every
     * customer. Any role that belongs to no organization may be granted this way: an
     * app-agnostic one reaches every app's tokens, one your app declared reaches only
     * your app's. An organization's own role is refused with `422 role_not_assignable`; a
     * grant that would form a segregation-of-duties conflict in any organization the
     * person belongs to with `409 role_conflict`.
     *
     * `PUT /users/{id}/environment-roles/{role_id}` · action `users.environment_roles.grant` · scope `roles:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<RoleAssignment>|PendingApprovalResult<ApiResponse<RoleAssignment>> : ApiResponse<RoleAssignment>)
     */
    public function grant(string $id, string $roleId, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.environment_roles.grant'), [$id, $roleId], $query, $options, Value::dto(RoleAssignment::fromArray(...)));
    }

    /**
     * List the roles a user holds everywhere (staff)
     *
     * `GET /users/{id}/environment-roles` · action `users.environment_roles.list` · scope `roles:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<RoleAssignment>>|PendingApprovalResult<ApiResponse<list<RoleAssignment>>> : ApiResponse<list<RoleAssignment>>)
     */
    public function list(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.environment_roles.list'), [$id], [], $options, Value::list(Value::dto(RoleAssignment::fromArray(...))));
    }

    /**
     * Take back an environment-wide grant
     *
     * Idempotent.
     *
     * `DELETE /users/{id}/environment-roles/{role_id}` · action `users.environment_roles.revoke` · scope `roles:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, string $roleId, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.environment_roles.revoke'), [$id, $roleId], $query, $options, Value::none(...));
    }
}
