<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `keys.*` on the environment plane. */
class Keys
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Mint a management key for this environment, at most as wide as the caller. The value is returned once, as `token`.
     *
     * `POST /keys` · action `keys.create` · scope `keys:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, scopes: list<string>, expires_at?: string|null, description?: string|null, require_approval?: array{min_danger?: 'write'|'destructive'|'critical'|null, actions?: list<string>}}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.create'), [], $body, $options, Value::object(...));
    }

    /**
     * List this environment's management keys (names, scopes, parents, expiry, last use — never their values).
     *
     * `GET /keys` · action `keys.list` · scope `keys:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<array<string, mixed>>|PendingApprovalResult<Page<array<string, mixed>>> : Page<array<string, mixed>>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('keys.list'), [], $query, $options, Value::object(...));
    }

    /**
     * Every item of `keys.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, array<string, mixed>, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('keys.list'), [], $query, $options, Value::object(...));
    }

    /**
     * Revoke a management key and every key it minted, immediately.
     *
     * `DELETE /keys/{id}` · action `keys.revoke` · scope `keys:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.revoke'), [$id], [], $options, Value::none(...));
    }

    /**
     * Rotate a management key: mint a successor with the same scopes and retire the old one after a grace period.
     *
     * `POST /keys/{id}/rotate` · action `keys.rotate` · scope `keys:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{grace_hours?: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function rotate(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.rotate'), [$id], $body, $options, Value::object(...));
    }
}
