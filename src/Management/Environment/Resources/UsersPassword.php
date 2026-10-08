<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\User;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `users.password.*` on the environment plane. */
class UsersPassword
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Set a user's password, temporary by default, and mail it to them unless told not to. Signs them out according to `revoke`.
     *
     * `POST /users/{id}/password` · action `users.password.set` · scope `users:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{password: string, reason: string, temporary?: bool, expires_in_hours?: int, revoke?: 'sessions_and_tokens'|'sessions_only'|'nothing', send_email?: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<User>|PendingApprovalResult<ApiResponse<User>> : ApiResponse<User>)
     */
    public function set(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.password.set'), [$id], $body, $options, Value::dto(User::fromArray(...)));
    }
}
