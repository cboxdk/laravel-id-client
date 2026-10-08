<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Permission;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `permissions.*` on the environment plane. */
class Permissions
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Author a manual `feature:action` permission, shared with the environment (organization_id null) or one organization's own.
     *
     * `POST /permissions` · action `permissions.create` · scope `role_definitions:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, description?: string|null, organization_id?: string|null, tenant_assignable?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Permission>|PendingApprovalResult<ApiResponse<Permission>> : ApiResponse<Permission>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('permissions.create'), [], $body, $options, Value::dto(Permission::fromArray(...)));
    }

    /**
     * Delete a manual permission: every role carrying it loses it first.
     *
     * `DELETE /permissions/{id}` · action `permissions.delete` · scope `role_definitions:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('permissions.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * List the permission catalogue roles are composed from, a page at a time. Narrow by `client_id` (one app's), `organization_id` (what it can see) or a `q` fragment.
     *
     * `GET /permissions` · action `permissions.list` · scope `roles:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string, organization_id?: string, client_id?: string, q?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Permission>|PendingApprovalResult<Page<Permission>> : Page<Permission>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('permissions.list'), [], $query, $options, Value::dto(Permission::fromArray(...)));
    }

    /**
     * Every item of `permissions.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int, organization_id?: string, client_id?: string, q?: string}  $query
     * @return Generator<int, Permission, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('permissions.list'), [], $query, $options, Value::dto(Permission::fromArray(...)));
    }

    /**
     * Change a manual permission's description, and on the shared tier whether organizations may use it. The key itself never changes.
     *
     * `PATCH /permissions/{id}` · action `permissions.update` · scope `role_definitions:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{description?: string|null, tenant_assignable?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Permission>|PendingApprovalResult<ApiResponse<Permission>> : ApiResponse<Permission>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('permissions.update'), [$id], $body, $options, Value::dto(Permission::fromArray(...)));
    }
}
