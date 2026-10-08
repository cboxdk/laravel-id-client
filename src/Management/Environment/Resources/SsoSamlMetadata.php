<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SamlMetadata;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `sso.saml_metadata.*` on the environment plane. */
class SsoSamlMetadata
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Parse an identity provider's SAML metadata (XML or a metadata URL) into the entity id, SSO URL and certificate a SAML connection needs. Stores nothing.
     *
     * `POST /sso/saml-metadata` · action `sso.saml_metadata.import` · scope `sso:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{metadata: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SamlMetadata>|PendingApprovalResult<ApiResponse<SamlMetadata>> : ApiResponse<SamlMetadata>)
     */
    public function import(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.saml_metadata.import'), [], $body, $options, Value::dto(SamlMetadata::fromArray(...)));
    }
}
