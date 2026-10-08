<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SamlApp;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `saml_apps.*` on the environment plane. */
class SamlApps
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Register a SAML app that signs people in with their account here. Decides where assertions — and their attributes — are sent.
     *
     * `POST /saml-apps` · action `saml_apps.create` · scope `saml_apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{entity_id: string, acs_url: string, name_id_format?: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'|'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'|'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'|'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified', name_id_attribute?: string, attribute_mappings?: list<array{attribute: string, field: string}>, want_authn_requests_signed?: bool, certificate?: string|null, organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SamlApp>|PendingApprovalResult<ApiResponse<SamlApp>> : ApiResponse<SamlApp>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('saml_apps.create'), [], $body, $options, Value::dto(SamlApp::fromArray(...)));
    }

    /**
     * Remove a SAML app. People can no longer sign in to it with their account here.
     *
     * `DELETE /saml-apps/{id}` · action `saml_apps.delete` · scope `saml_apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('saml_apps.delete'), [$id], [], $options, Value::none(...));
    }

    /**
     * Read one SAML app: its entity id, ACS URL, NameID and attribute mappings. Never its certificate.
     *
     * `GET /saml-apps/{id}` · action `saml_apps.get` · scope `saml_apps:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<SamlApp>|PendingApprovalResult<ApiResponse<SamlApp>> : ApiResponse<SamlApp>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('saml_apps.get'), [$id], [], $options, Value::dto(SamlApp::fromArray(...)));
    }

    /**
     * List the SAML apps that trust this environment as their identity provider.
     *
     * `GET /saml-apps` · action `saml_apps.list` · scope `saml_apps:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<SamlApp>|PendingApprovalResult<Page<SamlApp>> : Page<SamlApp>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('saml_apps.list'), [], $query, $options, Value::dto(SamlApp::fromArray(...)));
    }

    /**
     * Every item of `saml_apps.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int}  $query
     * @return Generator<int, SamlApp, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('saml_apps.list'), [], $query, $options, Value::dto(SamlApp::fromArray(...)));
    }

    /**
     * Change a SAML app's entity id, ACS URL, NameID, attribute mappings, signing certificate or owning organization.
     *
     * `PATCH /saml-apps/{id}` · action `saml_apps.update` · scope `saml_apps:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{entity_id?: string, acs_url?: string, name_id_format?: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'|'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'|'urn:oasis:names:tc:SAML:2.0:nameid-format:transient'|'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified', name_id_attribute?: string, attribute_mappings?: list<array{attribute: string, field: string}>, want_authn_requests_signed?: bool, certificate?: string|null, organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SamlApp>|PendingApprovalResult<ApiResponse<SamlApp>> : ApiResponse<SamlApp>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('saml_apps.update'), [$id], $body, $options, Value::dto(SamlApp::fromArray(...)));
    }
}
