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

/** `roles.permissions.*` on the environment plane. */
class RolesPermissions
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Add a permission to a role: everyone holding the role gains it. The permission must be unscoped or declared by the role's own app.
     *
     * `PUT /roles/{id}/permissions/{permission_id}` · action `roles.permissions.grant` · scope `role_definitions:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Role>|PendingApprovalResult<ApiResponse<Role>> : ApiResponse<Role>)
     */
    public function grant(string $id, string $permissionId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.permissions.grant'), [$id, $permissionId], [], $options, Value::dto(Role::fromArray(...)));
    }

    /**
     * Remove a permission from a role: everyone holding the role loses it.
     *
     * `DELETE /roles/{id}/permissions/{permission_id}` · action `roles.permissions.revoke` · scope `role_definitions:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Role>|PendingApprovalResult<ApiResponse<Role>> : ApiResponse<Role>)
     */
    public function revoke(string $id, string $permissionId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.permissions.revoke'), [$id, $permissionId], [], $options, Value::dto(Role::fromArray(...)));
    }
}
