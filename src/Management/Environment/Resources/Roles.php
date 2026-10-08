<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Role;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `roles.*` on the environment plane. */
class Roles
{
    public readonly RolesPermissions $permissions;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->permissions = new RolesPermissions($transport);
    }

    /**
     * Define a role for one organization, or (organization_id null) for the whole environment, optionally scoped to one app, with its opening permissions.
     *
     * `POST /roles` · action `roles.create` · scope `role_definitions:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, description?: string|null, organization_id?: string|null, client_id?: string|null, permissions?: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Role>|PendingApprovalResult<ApiResponse<Role>> : ApiResponse<Role>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.create'), [], $body, $options, Value::dto(Role::fromArray(...)));
    }

    /**
     * Delete a role: everyone who holds it loses it. An app-declared role is removed from the app's manifest instead.
     *
     * `DELETE /roles/{id}` · action `roles.delete` · scope `role_definitions:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Get one role and the permissions it carries. Name it by id, or by manifest `key` with `client_id`.
     *
     * `GET /roles/{id}` · action `roles.get` · scope `roles:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<Role>|PendingApprovalResult<ApiResponse<Role>> : ApiResponse<Role>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.get'), [$id], $query, $options, Value::dto(Role::fromArray(...)));
    }

    /**
     * List roles
     *
     * Every role that can still be granted, ordered by name,
     * with the permissions each carries. Not paginated. `?client_id=` narrows to one
     * app's roles; `?organization_id=` to what can be granted in that organization.
     *
     * `GET /roles` · action `roles.list` · scope `roles:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id?: string, organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<Role>>|PendingApprovalResult<ApiResponse<list<Role>>> : ApiResponse<list<Role>>)
     */
    public function list(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.list'), [], $query, $options, Value::list(Value::dto(Role::fromArray(...))));
    }

    /**
     * Rename a role or change its description. An app-declared role is changed in the app's manifest instead.
     *
     * `PATCH /roles/{id}` · action `roles.update` · scope `role_definitions:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name?: string, description?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Role>|PendingApprovalResult<ApiResponse<Role>> : ApiResponse<Role>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.update'), [$id], $body, $options, Value::dto(Role::fromArray(...)));
    }
}
