<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;
use Cbox\Id\Client\Management\Workspace\Schemas\WorkspaceKey;
use Generator;

/** `keys.workspace.*` on the workspace plane. */
class KeysWorkspace
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Mint a workspace key with a role and optional scopes, never wider than the caller. The value is returned once, as `token`.
     *
     * Requires scope `keys:write` and a role that may `manage-environments` and `manage-members`.
     *
     * `POST /workspace/keys` · action `keys.workspace.create` · scope `keys:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, role: 'admin'|'developer'|'member'|'viewer', scopes?: list<'workspace:read'|'projects:write'|'environments:write'|'team:read'|'team:write'|'keys:write'|'settings:write'>|null, expires_at?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<WorkspaceKey>|PendingApprovalResult<ApiResponse<WorkspaceKey>> : ApiResponse<WorkspaceKey>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.workspace.create'), [], $body, $options, Value::dto(WorkspaceKey::fromArray(...)));
    }

    /**
     * List the workspace's keys, newest first, revoked ones included — names, roles, scopes and expiry, never their values.
     *
     * Requires scope `workspace:read` and a role that may `manage-members`.
     *
     * `GET /workspace/keys` · action `keys.workspace.list` · scope `workspace:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, page?: int}  $query
     * @return ($options is ReturnPendingApproval ? Page<WorkspaceKey>|PendingApprovalResult<Page<WorkspaceKey>> : Page<WorkspaceKey>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('keys.workspace.list'), [], $query, $options, Value::dto(WorkspaceKey::fromArray(...)));
    }

    /**
     * Every item of `keys.workspace.list`, fetching pages lazily as the iteration reaches them — `page` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, WorkspaceKey, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('keys.workspace.list'), [], $query, $options, Value::dto(WorkspaceKey::fromArray(...)));
    }

    /**
     * Revoke a workspace key, and every key it minted; whatever uses them stops immediately.
     *
     * Requires scope `keys:write` and a role that may `manage-environments` and `manage-members`.
     *
     * `DELETE /workspace/keys/{id}` · action `keys.workspace.revoke` · scope `keys:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.workspace.revoke'), [$id], [], $options, Value::none(...));
    }
}
