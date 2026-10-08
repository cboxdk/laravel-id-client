<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SsoDomain;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `sso.domains.*` on the environment plane. */
class SsoDomains
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Turn capture on or off for a verified domain: captured, everyone with an address at it must sign in through the organization's SSO.
     *
     * `POST /sso/domains/{id}/capture` · action `sso.domains.capture` · scope `sso:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{capture: bool, organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoDomain>|PendingApprovalResult<ApiResponse<SsoDomain>> : ApiResponse<SsoDomain>)
     */
    public function capture(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.domains.capture'), [$id], $body, $options, Value::dto(SsoDomain::fromArray(...)));
    }

    /**
     * Claim an email domain for an organization's single sign-on, and get the DNS TXT record to publish to prove it.
     *
     * `POST /sso/domains` · action `sso.domains.create` · scope `sso:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id: string, domain: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoDomain>|PendingApprovalResult<ApiResponse<SsoDomain>> : ApiResponse<SsoDomain>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.domains.create'), [], $body, $options, Value::dto(SsoDomain::fromArray(...)));
    }

    /**
     * Remove a claimed email domain. Its people stop being routed to the organization's SSO connection.
     *
     * `DELETE /sso/domains/{id}` · action `sso.domains.delete` · scope `sso:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.domains.delete'), [$id], $body, $options, Value::none(...));
    }

    /**
     * List the email domains organizations claimed for single sign-on, whether each is verified and captured, and the DNS record that proves an unverified one.
     *
     * `GET /sso/domains` · action `sso.domains.list` · scope `sso:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<SsoDomain>|PendingApprovalResult<Page<SsoDomain>> : Page<SsoDomain>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('sso.domains.list'), [], $query, $options, Value::dto(SsoDomain::fromArray(...)));
    }

    /**
     * Every item of `sso.domains.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, SsoDomain, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('sso.domains.list'), [], $query, $options, Value::dto(SsoDomain::fromArray(...)));
    }

    /**
     * Check a claimed domain's DNS TXT record now. Answers the domain, with verified true once the record is found.
     *
     * `POST /sso/domains/{id}/verify` · action `sso.domains.verify` · scope `sso:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SsoDomain>|PendingApprovalResult<ApiResponse<SsoDomain>> : ApiResponse<SsoDomain>)
     */
    public function verify(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sso.domains.verify'), [$id], $body, $options, Value::dto(SsoDomain::fromArray(...)));
    }
}
