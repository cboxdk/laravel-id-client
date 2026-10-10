<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SignInPolicy as SignInPolicySchema;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `signin.policy.*` on the environment plane. */
class SigninPolicy
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Read the authentication policy (password, MFA, SSO, lockout): the environment baseline, or one organization's effective rules and override.
     *
     * `GET /sign-in/policy` · action `signin.policy.get` · scope `signin:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<SignInPolicySchema>|PendingApprovalResult<ApiResponse<SignInPolicySchema>> : ApiResponse<SignInPolicySchema>)
     */
    public function get(array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.policy.get'), [], $query, $options, Value::dto(SignInPolicySchema::fromArray(...)));
    }

    /**
     * Drop one organization's authentication policy override, so it inherits the environment baseline again.
     *
     * `DELETE /sign-in/policy/organizations/{organization_id}` · action `signin.policy.inherit` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function inherit(string $organizationId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.policy.inherit'), [$organizationId], [], $options, Value::none(...));
    }

    /**
     * Change the authentication policy of the environment baseline — including whether passkeys and magic links are offered and how long sessions last — or tighten one organization's override. Requiring SSO signs out password sessions.
     *
     * `PATCH /sign-in/policy` · action `signin.policy.update` · scope `signin:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, min_length?: int, require_breach_check?: bool, max_age_days?: int|null, reuse_history?: int, mfa?: 'off'|'optional'|'required', sso?: 'off'|'preferred'|'required', lockout_threshold?: int|null, passkeys?: bool, magic_link?: bool, session_idle_minutes?: int|null, session_absolute_minutes?: int|null, bot_challenge?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SignInPolicySchema>|PendingApprovalResult<ApiResponse<SignInPolicySchema>> : ApiResponse<SignInPolicySchema>)
     */
    public function update(array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('signin.policy.update'), [], $body, $options, Value::dto(SignInPolicySchema::fromArray(...)));
    }
}
