<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Api;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `apis.*` on the environment plane. */
class Apis
{
    public readonly ApisScopes $scopes;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->scopes = new ApisScopes($transport);
    }

    /**
     * Register an API and its scopes
     *
     * Registering an API is the environment's act; no tenant
     * surface can. `identifier` is an absolute URI (RFC 8707) and becomes the token's
     * `aud`. Scope keys are unique across the environment. `client_id` links the app whose
     * roles and permissions the API enforces, and must have the API's owner.
     * `organization_id` makes the API one organization's; left out, the environment owns
     * it. Refusals are `422 invalid_api` with the reason.
     *
     * `POST /apis` · action `apis.create` · scope `apis:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{identifier: string, name: string, organization_id?: string|null, client_id?: string|null, scopes?: list<array{key: string, description?: string|null, tenant_requestable?: bool}>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Api>|PendingApprovalResult<ApiResponse<Api>> : ApiResponse<Api>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apis.create'), [], $body, $options, Value::dto(Api::fromArray(...)));
    }

    /**
     * Delete an API and its scopes
     *
     * Tokens already minted for it keep their `aud` until they
     * expire; apps holding its scope keys keep them as plain scopes.
     *
     * `DELETE /apis/{id}` · action `apis.delete` · scope `apis:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apis.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Get an API
     *
     * `GET /apis/{id}` · action `apis.get` · scope `apis:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Api>|PendingApprovalResult<ApiResponse<Api>> : ApiResponse<Api>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apis.get'), [$id], [], $options, Value::dto(Api::fromArray(...)));
    }

    /**
     * List registered APIs
     *
     * `GET /apis` · action `apis.list` · scope `apis:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<Api>|PendingApprovalResult<Page<Api>> : Page<Api>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('apis.list'), [], $query, $options, Value::dto(Api::fromArray(...)));
    }

    /**
     * Every item of `apis.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, Api, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('apis.list'), [], $query, $options, Value::dto(Api::fromArray(...)));
    }

    /**
     * Change an API
     *
     * Any of: `name`; `client_id` (null unlinks the app);
     * `scopes`, which is the **complete** set afterwards — scopes named are added or
     * updated, scopes left out are removed. All or nothing. The identifier never changes:
     * register a new API instead.
     *
     * `PATCH /apis/{id}` · action `apis.update` · scope `apis:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name?: string, client_id?: string|null, scopes?: list<array{key: string, description?: string|null, tenant_requestable?: bool}>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Api>|PendingApprovalResult<ApiResponse<Api>> : ApiResponse<Api>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apis.update'), [$id], $body, $options, Value::dto(Api::fromArray(...)));
    }
}
