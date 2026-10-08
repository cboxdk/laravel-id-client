<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SignInPolicy;
use Cbox\Id\Client\Management\Environment\Schemas\SsoConnection;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `sso.connections.*` on the environment plane. */
class SsoConnections
{
    public readonly SsoConnectionsCertificates $certificates;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->certificates = new SsoConnectionsCertificates($transport);
    }

    /**
     * Activate an SSO connection: people whose email domain routes to it start signing in through it.
     *
     * `POST /sso/connections/{id}/activate` · action `sso.connections.activate` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoConnection>|PendingApprovalResult<ApiResponse<SsoConnection>> : ApiResponse<SsoConnection>)
     */
    public function activate(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.activate'), [$id], $body, $options, Value::dto(SsoConnection::fromArray(...)));
    }

    /**
     * Connect an organization's SAML or OIDC identity provider as a draft. Activate it to start signing people in through it.
     *
     * `POST /sso/connections` · action `sso.connections.create` · scope `sso:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, environment_wide?: bool, name: string, type: 'saml'|'oidc', pending_idp?: bool, idp_entity_id?: string|null, idp_sso_url?: string|null, idp_x509cert?: string|null, sp_entity_id?: string|null, sp_acs_url?: string|null, issuer?: string|null, client_id?: string|null, client_secret?: string|null, signing_key?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoConnection>|PendingApprovalResult<ApiResponse<SsoConnection>> : ApiResponse<SsoConnection>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.create'), [], $body, $options, Value::dto(SsoConnection::fromArray(...)));
    }

    /**
     * Delete an SSO connection and its settings. Where SSO is required, its people cannot sign in until another is activated.
     *
     * `DELETE /sso/connections/{id}` · action `sso.connections.delete` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.delete'), [$id], $body, $options, Value::none(...));
    }

    /**
     * Disable an SSO connection without deleting it. Where SSO is required, its people cannot sign in until it is re-activated.
     *
     * `POST /sso/connections/{id}/disable` · action `sso.connections.disable` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoConnection>|PendingApprovalResult<ApiResponse<SsoConnection>> : ApiResponse<SsoConnection>)
     */
    public function disable(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.disable'), [$id], $body, $options, Value::dto(SsoConnection::fromArray(...)));
    }

    /**
     * Read one SSO connection: its type, status, entity ids, URLs, issuer and client id. Never a certificate or secret.
     *
     * `GET /sso/connections/{id}` · action `sso.connections.get` · scope `sso:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoConnection>|PendingApprovalResult<ApiResponse<SsoConnection>> : ApiResponse<SsoConnection>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.get'), [$id], $query, $options, Value::dto(SsoConnection::fromArray(...)));
    }

    /**
     * List the SAML and OIDC connections organizations sign in through, optionally for one organization. Never a certificate or secret.
     *
     * `GET /sso/connections` · action `sso.connections.list` · scope `sso:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<SsoConnection>|PendingApprovalResult<Page<SsoConnection>> : Page<SsoConnection>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('sso.connections.list'), [], $query, $options, Value::dto(SsoConnection::fromArray(...)));
    }

    /**
     * Every item of `sso.connections.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, SsoConnection, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('sso.connections.list'), [], $query, $options, Value::dto(SsoConnection::fromArray(...)));
    }

    /**
     * Require single sign-on for the organization an SSO connection belongs to. Ends every password session there.
     *
     * `POST /sso/connections/{id}/require-sso` · action `sso.connections.require_sso` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SignInPolicy>|PendingApprovalResult<ApiResponse<SignInPolicy>> : ApiResponse<SignInPolicy>)
     */
    public function requireSso(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.require_sso'), [$id], $body, $options, Value::dto(SignInPolicy::fromArray(...)));
    }

    /**
     * Change an SSO connection's name or identity-provider settings. Secrets left out keep the ones on file.
     *
     * `PATCH /sso/connections/{id}` · action `sso.connections.update` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, name?: string, idp_entity_id?: string|null, idp_sso_url?: string|null, idp_x509cert?: string|null, sp_entity_id?: string|null, sp_acs_url?: string|null, issuer?: string|null, client_id?: string|null, client_secret?: string|null, signing_key?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoConnection>|PendingApprovalResult<ApiResponse<SsoConnection>> : ApiResponse<SsoConnection>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.update'), [$id], $body, $options, Value::dto(SsoConnection::fromArray(...)));
    }
}
