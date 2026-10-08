<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\OrganizationDomain;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `organizations.domains.*` on the environment plane. */
class OrganizationsDomains
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Claim an email domain (acme.com) for an organization. Returns the DNS TXT record to publish before verifying it.
     *
     * `POST /organizations/{organization_id}/domains` · action `organizations.domains.add` · scope `organizations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{domain: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<OrganizationDomain>|PendingApprovalResult<ApiResponse<OrganizationDomain>> : ApiResponse<OrganizationDomain>)
     */
    public function add(string $organizationId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.domains.add'), [$organizationId], $body, $options, Value::dto(OrganizationDomain::fromArray(...)));
    }

    /**
     * Turn capture on or off for a verified domain: with it on, everyone with an address there signs in through the organization's SSO.
     *
     * `PUT /organizations/{organization_id}/domains/{domain_id}/capture` · action `organizations.domains.capture` · scope `organizations:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{enabled: bool}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<OrganizationDomain>|PendingApprovalResult<ApiResponse<OrganizationDomain>> : ApiResponse<OrganizationDomain>)
     */
    public function capture(string $organizationId, string $domainId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.domains.capture'), [$organizationId, $domainId], $body, $options, Value::dto(OrganizationDomain::fromArray(...)));
    }

    /**
     * List the email domains an organization claims
     *
     * Verified or not, each with the DNS TXT record
     * that proves it (`record_name`, `record_value`). A handful at most; not paged.
     *
     * `GET /organizations/{organization_id}/domains` · action `organizations.domains.list` · scope `organizations:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<list<OrganizationDomain>>|PendingApprovalResult<ApiResponse<list<OrganizationDomain>>> : ApiResponse<list<OrganizationDomain>>)
     */
    public function list(string $organizationId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.domains.list'), [$organizationId], [], $options, Value::list(Value::dto(OrganizationDomain::fromArray(...))));
    }

    /**
     * Remove a claimed email domain from an organization. Capture on it ends.
     *
     * `DELETE /organizations/{organization_id}/domains/{domain_id}` · action `organizations.domains.remove` · scope `organizations:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function remove(string $organizationId, string $domainId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.domains.remove'), [$organizationId, $domainId], [], $options, Value::none(...));
    }

    /**
     * Check a claimed domain's DNS TXT record and mark the domain verified if it is published.
     *
     * `POST /organizations/{organization_id}/domains/{domain_id}/verify` · action `organizations.domains.verify` · scope `organizations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<OrganizationDomain>|PendingApprovalResult<ApiResponse<OrganizationDomain>> : ApiResponse<OrganizationDomain>)
     */
    public function verify(string $organizationId, string $domainId, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.domains.verify'), [$organizationId, $domainId], [], $options, Value::dto(OrganizationDomain::fromArray(...)));
    }
}
