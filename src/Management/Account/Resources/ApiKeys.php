<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Resources;

use Cbox\Id\Client\Management\Account\Operations;
use Cbox\Id\Client\Management\Account\Schemas\PersonalApiKey;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `api_keys.*` on the account plane. */
class ApiKeys
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create an API key of your own for one of this environment's apps, in an organization you belong to. The value is returned once, as `token`.
     *
     * Requires scope `account:api_keys:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `POST /me/organizations/{organization_id}/api-keys` · action `account.api_keys.create` · scope `account:api_keys:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{client_id: string, name: string, permissions?: list<string>, expires_at?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PersonalApiKey>|PendingApprovalResult<ApiResponse<PersonalApiKey>> : ApiResponse<PersonalApiKey>)
     */
    public function create(string $organizationId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.api_keys.create'), [$organizationId], $body, $options, Value::dto(PersonalApiKey::fromArray(...)));
    }

    /**
     * Revoke one of your own API keys.
     *
     * Requires scope `account:api_keys:write` on an access token you delegated; it acts on your own account only. No management key is accepted.
     *
     * `DELETE /me/organizations/{organization_id}/api-keys/{key_id}` · action `account.api_keys.revoke` · scope `account:api_keys:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function revoke(string $organizationId, string $keyId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('account.api_keys.revoke'), [$organizationId, $keyId], [], $options, Value::none(...));
    }
}
