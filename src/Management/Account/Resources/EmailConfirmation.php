<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `email.confirmation.*` on the account plane. */
class EmailConfirmation
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Mail yourself a link that confirms your email address.
     *
     * Requires scope `account:profile:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `POST /me/email/confirmation` · action `account.email.confirmation.send` · scope `account:profile:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<array<string, mixed>>|PendingApprovalResult<ApiResponse<array<string, mixed>>> : ApiResponse<array<string, mixed>>)
     */
    public function send(?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.email.confirmation.send'), [], [], $options, Value::object(...));
    }
}
