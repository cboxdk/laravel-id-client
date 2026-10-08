<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\PortalLink;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `organizations.portal_links.*` on the environment plane. */
class OrganizationsPortalLinks
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create a one-time Admin Portal link an organization's IT administrator uses to set up SSO, directory sync, domain verification, log streams or SAML certificate renewal, or to read its audit logs, without an account. The URL is shown once.
     *
     * `POST /organizations/{organization_id}/portal-links` · action `organizations.portal_links.create` · scope `portal_links:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{intents: list<'sso'|'dsync'|'domain_verification'|'log_streams'|'certificate_renewal'|'audit_logs'>, expires_in_minutes?: int|null, email?: string|null, locale?: 'en'|'da'|'de'|'sv'|'nb'|'fr'|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PortalLink>|PendingApprovalResult<ApiResponse<PortalLink>> : ApiResponse<PortalLink>)
     */
    public function create(string $organizationId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.portal_links.create'), [$organizationId], $body, $options, Value::dto(PortalLink::fromArray(...)));
    }
}
