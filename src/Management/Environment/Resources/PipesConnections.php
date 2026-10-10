<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\PipeConnection;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `pipes.connections.*` on the environment plane. */
class PipesConnections
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Disconnect a person's connected account: revoke it at the provider where supported, revoke its tokens here, and forget it.
     *
     * `DELETE /pipes/{id}/connections/{connection_id}` · action `pipes.connections.delete` · scope `pipes:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, string $connectionId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('pipes.connections.delete'), [$id, $connectionId], [], $options, Value::none(...));
    }

    /**
     * List the accounts people connected through a pipe: who, which account, granted scopes, status (active / needs_reauth) and when the token expires. Never a token.
     *
     * `GET /pipes/{id}/connections` · action `pipes.connections.list` · scope `pipes:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{user_id?: string, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<PipeConnection>|PendingApprovalResult<Page<PipeConnection>> : Page<PipeConnection>)
     */
    public function list(string $id, array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('pipes.connections.list'), [$id], $query, $options, Value::dto(PipeConnection::fromArray(...)));
    }

    /**
     * Every item of `pipes.connections.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{user_id?: string, limit?: int}  $query
     * @return Generator<int, PipeConnection, mixed, void>
     */
    public function listAll(string $id, array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('pipes.connections.list'), [$id], $query, $options, Value::dto(PipeConnection::fromArray(...)));
    }
}
