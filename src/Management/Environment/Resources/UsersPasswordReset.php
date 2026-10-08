<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `users.password_reset.*` on the environment plane. */
class UsersPasswordReset
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Mail a user a password-reset link at their own address.
     *
     * `POST /users/{id}/password-reset` · action `users.password_reset.send` · scope `users:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function send(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.password_reset.send'), [$id], [], $options, Value::none(...));
    }
}
