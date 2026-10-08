<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\AppSecret;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `apps.secrets.*` on the environment plane. */
class AppsSecrets
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List an app's live client secrets
     *
     * Every live secret, newest first: its id (what
     * `DELETE /apps/{id}/secrets/{secret_id}` names), the characters it ends in, and its
     * dates — never a secret. `expires_at` is set on one a rotation replaced, still working
     * through its overlap. An app holds a handful at most, so there is no page to turn.
     *
     * `GET /apps/{id}/secrets` · action `apps.secrets.list` · scope `apps:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<AppSecret>>|PendingApprovalResult<ApiResponse<list<AppSecret>>> : ApiResponse<list<AppSecret>>)
     */
    public function list(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.secrets.list'), [$id], [], $options, Value::list(Value::dto(AppSecret::fromArray(...))));
    }

    /**
     * Revoke one of an app's client secrets immediately. Never its last live secret — rotate that instead.
     *
     * `DELETE /apps/{id}/secrets/{secret_id}` · action `apps.secrets.revoke` · scope `apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, string $secretId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.secrets.revoke'), [$id, $secretId], [], $options, Value::none(...));
    }

    /**
     * Mint a new client secret for an app and retire the current ones after grace_seconds (0 = at once). The new secret is returned once.
     *
     * `POST /apps/{id}/secrets` · action `apps.secrets.rotate` · scope `apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{grace_seconds: int}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<AppSecret>|PendingApprovalResult<ApiResponse<AppSecret>> : ApiResponse<AppSecret>)
     */
    public function rotate(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('apps.secrets.rotate'), [$id], $body, $options, Value::dto(AppSecret::fromArray(...)));
    }
}
