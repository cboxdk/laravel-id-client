<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\UserSession;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `users.sessions.*` on the environment plane. */
class UsersSessions
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * List a user's live sign-in sessions
     *
     * The fifty most recently active, newest first — enough to
     * recognise a device and end one (`DELETE /users/{id}/sessions/{session_id}`), not a log.
     * `impersonation` marks a session somebody else opened as this person.
     *
     * `GET /users/{id}/sessions` · action `users.sessions.list` · scope `users:read`
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<UserSession>>|PendingApprovalResult<ApiResponse<list<UserSession>>> : ApiResponse<list<UserSession>>)
     */
    public function list(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.sessions.list'), [$id], [], $options, Value::list(Value::dto(UserSession::fromArray(...))));
    }

    /**
     * End one of a user's sign-in sessions.
     *
     * `DELETE /users/{id}/sessions/{session_id}` · action `users.sessions.revoke` · scope `users:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $id, string $sessionId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.sessions.revoke'), [$id, $sessionId], [], $options, Value::none(...));
    }

    /**
     * Sign a user out everywhere: end every session and revoke every OAuth grant they hold.
     *
     * `DELETE /users/{id}/sessions` · action `users.sessions.revoke_all` · scope `users:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revokeAll(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.sessions.revoke_all'), [$id], [], $options, Value::none(...));
    }
}
