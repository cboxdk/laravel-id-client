<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\ApiKey;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `api_keys.*` on the environment plane. */
class ApiKeys
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List the API keys an organization's members created for your apps
     *
     * Revoked and expired keys are included, marked by
     * `status`. Never the key itself. Narrow to one app with `?client_id=`.
     *
     * `GET /organizations/{organization_id}/api-keys` · action `api_keys.list` · scope `api_keys:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string, client_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<ApiKey>|PendingApprovalResult<Page<ApiKey>> : Page<ApiKey>)
     */
    public function list(string $organizationId, array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('api_keys.list'), [$organizationId], $query, $options, Value::dto(ApiKey::fromArray(...)));
    }

    /**
     * Every item of `api_keys.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int, client_id?: string}  $query
     * @return Generator<int, ApiKey, mixed, void>
     */
    public function listAll(string $organizationId, array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('api_keys.list'), [$organizationId], $query, $options, Value::dto(ApiKey::fromArray(...)));
    }

    /**
     * Revoke a member API key
     *
     * The key stops verifying at once. Idempotent — an
     * already-revoked key is a 204 too. Recorded as `api_key.revoked`, with this
     * management key as the actor.
     *
     * `DELETE /api-keys/{id}` · action `api_keys.revoke` · scope `api_keys:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('api_keys.revoke'), [$id], [], $options, Value::none(...));
    }
}
