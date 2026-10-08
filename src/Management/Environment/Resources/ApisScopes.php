<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Api;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `apis.scopes.*` on the environment plane. */
class ApisScopes
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Add a scope to an API, or change the description or tenant access of one it owns.
     *
     * `PUT /apis/{id}/scopes/{key}` · action `apis.scopes.define` · scope `apis:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{description?: string|null, tenant_requestable?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Api>|PendingApprovalResult<ApiResponse<Api>> : ApiResponse<Api>)
     */
    public function define(string $id, string $key, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apis.scopes.define'), [$id, $key], $body, $options, Value::dto(Api::fromArray(...)));
    }

    /**
     * Remove a scope from an API. Apps holding it keep it as a plain scope that no longer reaches the API.
     *
     * `DELETE /apis/{id}/scopes/{key}` · action `apis.scopes.remove` · scope `apis:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $id, string $key, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apis.scopes.remove'), [$id, $key], [], $options, Value::none(...));
    }
}
