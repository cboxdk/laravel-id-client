<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Account\Schemas\SessionsRevoked;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `sessions.*` on the account plane. */
class Sessions
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Sign out one of your own sessions.
     *
     * Requires scope `account:sessions:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `DELETE /me/sessions/{session_id}` · action `account.sessions.revoke` · scope `account:sessions:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $sessionId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.sessions.revoke'), [$sessionId], [], $options, Value::none(...));
    }

    /**
     * Sign out every one of your sessions except the one you are using.
     *
     * Requires scope `account:sessions:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `POST /me/sessions/revoke-others` · action `account.sessions.revoke_others` · scope `account:sessions:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<SessionsRevoked>|PendingApprovalResult<ApiResponse<SessionsRevoked>> : ApiResponse<SessionsRevoked>)
     */
    public function revokeOthers(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.sessions.revoke_others'), [], [], $options, Value::dto(SessionsRevoked::fromArray(...)));
    }
}
