<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\DirectoryGroup;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `directories.groups.*` on the environment plane. */
class DirectoriesGroups
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List the groups a directory has synced, with the role ids each group is mapped onto.
     *
     * `GET /directories/{id}/groups` · action `directories.groups.list` · scope `directory_sync:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<DirectoryGroup>|PendingApprovalResult<Page<DirectoryGroup>> : Page<DirectoryGroup>)
     */
    public function list(string $id, array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('directories.groups.list'), [$id], $query, $options, Value::dto(DirectoryGroup::fromArray(...)));
    }

    /**
     * Every item of `directories.groups.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, DirectoryGroup, mixed, void>
     */
    public function listAll(string $id, array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('directories.groups.list'), [$id], $query, $options, Value::dto(DirectoryGroup::fromArray(...)));
    }

    /**
     * Map a directory group onto a role (everyone in the group holds it as membership syncs), or unmap it.
     *
     * `POST /directories/{id}/group-roles` · action `directories.groups.map` · scope `directory_sync:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, group_id: string, role_id: string, mapped: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<DirectoryGroup>|PendingApprovalResult<ApiResponse<DirectoryGroup>> : ApiResponse<DirectoryGroup>)
     */
    public function map(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('directories.groups.map'), [$id], $body, $options, Value::dto(DirectoryGroup::fromArray(...)));
    }
}
