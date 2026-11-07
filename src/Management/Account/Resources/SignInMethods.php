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

/** `sign_in_methods.*` on the account plane. */
class SignInMethods
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Disconnect one linked sign-in identity (Google, GitHub, …) from your account — never the last way you can sign in.
     *
     * Requires scope `account:sign_in:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `DELETE /me/sign-in-methods/{identity_id}` · action `account.sign_in_methods.disconnect` · scope `account:sign_in:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function disconnect(string $identityId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.sign_in_methods.disconnect'), [$identityId], [], $options, Value::none(...));
    }
}
