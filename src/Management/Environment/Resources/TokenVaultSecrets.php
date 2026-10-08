<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\TokenVaultSecret;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `token_vault.secrets.*` on the environment plane. */
class TokenVaultSecrets
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Store a downstream credential (a third-party API key) in an organization's token vault, or the environment's own. The value is sealed and never returned.
     *
     * `POST /token-vault/secrets` · action `token_vault.secrets.create` · scope `token_vault:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, name: string, provider: string, secret: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<TokenVaultSecret>|PendingApprovalResult<ApiResponse<TokenVaultSecret>> : ApiResponse<TokenVaultSecret>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('token_vault.secrets.create'), [], $body, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }

    /**
     * Read one stored credential and the client ids granted to lease it. Never its value.
     *
     * `GET /token-vault/secrets/{id}` · action `token_vault.secrets.get` · scope `token_vault:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<TokenVaultSecret>|PendingApprovalResult<ApiResponse<TokenVaultSecret>> : ApiResponse<TokenVaultSecret>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('token_vault.secrets.get'), [$id], $query, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }

    /**
     * List the downstream credentials stored in an organization's token vault (or the environment's own): names, providers, status. Never a value.
     *
     * `GET /token-vault/secrets` · action `token_vault.secrets.list` · scope `token_vault:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<TokenVaultSecret>|PendingApprovalResult<Page<TokenVaultSecret>> : Page<TokenVaultSecret>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('token_vault.secrets.list'), [], $query, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }

    /**
     * Every item of `token_vault.secrets.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, TokenVaultSecret, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('token_vault.secrets.list'), [], $query, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }

    /**
     * Revoke a stored credential permanently. No app can lease it again.
     *
     * `POST /token-vault/secrets/{id}/revoke` · action `token_vault.secrets.revoke` · scope `token_vault:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<TokenVaultSecret>|PendingApprovalResult<ApiResponse<TokenVaultSecret>> : ApiResponse<TokenVaultSecret>)
     */
    public function revoke(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('token_vault.secrets.revoke'), [$id], $body, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }

    /**
     * Replace a stored credential's value. Every later lease hands out the new one. The value is never returned.
     *
     * `POST /token-vault/secrets/{id}/rotate` · action `token_vault.secrets.rotate` · scope `token_vault:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, secret: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<TokenVaultSecret>|PendingApprovalResult<ApiResponse<TokenVaultSecret>> : ApiResponse<TokenVaultSecret>)
     */
    public function rotate(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('token_vault.secrets.rotate'), [$id], $body, $options, Value::dto(TokenVaultSecret::fromArray(...)));
    }
}
