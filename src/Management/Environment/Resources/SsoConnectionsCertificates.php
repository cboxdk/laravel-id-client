<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SsoCertificates;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `sso.connections.certificates.*` on the environment plane. */
class SsoConnectionsCertificates
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Make a staged SAML signing certificate the connection's primary. The certificate it replaces stops being trusted.
     *
     * `POST /sso/connections/{id}/certificates/activate` · action `sso.connections.certificates.activate` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, fingerprint_sha256: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoCertificates>|PendingApprovalResult<ApiResponse<SsoCertificates>> : ApiResponse<SsoCertificates>)
     */
    public function activate(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.certificates.activate'), [$id], $body, $options, Value::dto(SsoCertificates::fromArray(...)));
    }

    /**
     * List a SAML connection's signing certificates — primary and staged — with each one's fingerprint and expiry.
     *
     * `GET /sso/connections/{id}/certificates` · action `sso.connections.certificates.list` · scope `sso:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoCertificates>|PendingApprovalResult<ApiResponse<SsoCertificates>> : ApiResponse<SsoCertificates>)
     */
    public function list(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.certificates.list'), [$id], $query, $options, Value::dto(SsoCertificates::fromArray(...)));
    }

    /**
     * Stage a SAML connection's new IdP signing certificate (PEM or IdP metadata) beside the current one, so a renewal has no outage. Activate it once the IdP signs with it.
     *
     * `POST /sso/connections/{id}/certificates` · action `sso.connections.certificates.stage` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, certificate?: string|null, metadata?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoCertificates>|PendingApprovalResult<ApiResponse<SsoCertificates>> : ApiResponse<SsoCertificates>)
     */
    public function stage(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.connections.certificates.stage'), [$id], $body, $options, Value::dto(SsoCertificates::fromArray(...)));
    }
}
