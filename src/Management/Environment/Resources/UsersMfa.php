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

/** `users.mfa.*` on the environment plane. */
class UsersMfa
{
    public readonly UsersMfaSms $sms;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->sms = new UsersMfaSms($transport);
    }

    /**
     * Reset a user's two-factor authentication: their authenticator and recovery codes are removed and they must enrol again.
     *
     * `DELETE /users/{id}/mfa` · action `users.mfa.reset` · scope `users:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function reset(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('users.mfa.reset'), [$id], [], $options, Value::none(...));
    }
}
