<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Resources;

use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Cbox\Id\Client\Management\Workspace\Operations;
use Cbox\Id\Client\Management\Workspace\Schemas\EnvironmentKey;

/** `keys.environment.*` on the workspace plane. */
class KeysEnvironment
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Mint a management key for one of the workspace's environments, with the scopes it needs. The value is returned once, as `token`.
     *
     * Requires scope `keys:write` and a role that may `manage-environments`.
     *
     * `POST /workspace/environments/{environment_id}/keys` · action `keys.environment.create` · scope `keys:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, scopes: list<string>, expires_at?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<EnvironmentKey>|PendingApprovalResult<ApiResponse<EnvironmentKey>> : ApiResponse<EnvironmentKey>)
     */
    public function create(string $environmentId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.environment.create'), [$environmentId], $body, $options, Value::dto(EnvironmentKey::fromArray(...)));
    }

    /**
     * Revoke one of an environment's management keys; whatever uses it stops immediately.
     *
     * Requires scope `keys:write` and a role that may `manage-environments`.
     *
     * `DELETE /workspace/environments/{environment_id}/keys/{id}` · action `keys.environment.revoke` · scope `keys:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $environmentId, string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('keys.environment.revoke'), [$environmentId, $id], [], $options, Value::none(...));
    }
}
