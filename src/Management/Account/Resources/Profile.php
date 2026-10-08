<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Account\Schemas\Profile as ProfileSchema;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `profile.*` on the account plane. */
class Profile
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Change the display name on your own account.
     *
     * Requires scope `account:profile:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `PATCH /me/profile` · action `account.profile.update` · scope `account:profile:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<ProfileSchema>|PendingApprovalResult<ApiResponse<ProfileSchema>> : ApiResponse<ProfileSchema>)
     */
    public function update(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.profile.update'), [], $body, $options, Value::dto(ProfileSchema::fromArray(...)));
    }
}
