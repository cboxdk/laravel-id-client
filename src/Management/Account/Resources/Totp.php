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

/** `totp.*` on the account plane. */
class Totp
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Remove one of your authenticator apps.
     *
     * Requires scope `account:sign_in:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `DELETE /me/two-factor/apps/{factor_id}` · action `account.totp.remove` · scope `account:sign_in:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $factorId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.totp.remove'), [$factorId], [], $options, Value::none(...));
    }

    /**
     * Rename one of your authenticator apps.
     *
     * Requires scope `account:sign_in:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `PATCH /me/two-factor/apps/{factor_id}` · action `account.totp.rename` · scope `account:sign_in:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function rename(string $factorId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.totp.rename'), [$factorId], $body, $options, Value::none(...));
    }
}
