<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\TokenVaultSecret;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `token_vault.grants.*` on the environment plane. */
class TokenVaultGrants
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Grant an app (by OAuth client id) the right to lease a stored credential.
     *
     * `POST /token-vault/secrets/{id}/grants` · action `token_vault.grants.create` · scope `token_vault:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, client_id: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<TokenVaultSecret>|PendingApprovalResult<ApiResponse<TokenVaultSecret>> : ApiResponse<TokenVaultSecret>)
     */
    public function create(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('token_vault.grants.create'), [$id], $body, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }

    /**
     * Withdraw an app's right to lease a stored credential.
     *
     * `DELETE /token-vault/secrets/{id}/grants/{client_id}` · action `token_vault.grants.delete` · scope `token_vault:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, string $clientId, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('token_vault.grants.delete'), [$id, $clientId], $body, $options, Value::none(...));
    }
}
