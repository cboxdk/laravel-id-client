<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Member;
use Cbox\Id\Client\Management\Environment\Schemas\Organization;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `organizations.*` on the environment plane. */
class Organizations
{
    public readonly OrganizationsDomains $domains;

    public readonly OrganizationsPortalLinks $portalLinks;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->domains = new OrganizationsDomains($transport);
        $this->portalLinks = new OrganizationsPortalLinks($transport);
    }

    /**
     * Create an organization, optionally with its owner
     *
     * `POST /organizations` · action `organizations.create` · scope `organizations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, slug?: string|null, parent_id?: string|null, owner_user_id?: string|null, type?: 'customer'|'reseller', metadata?: array<string, mixed>|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.create'), [], $body, $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * Archive an organization
     *
     * **Archives** rather than erases: the status
     * becomes `deleted`, every member loses access from their next request, the rows stay
     * for the audit trail, and `organization.deleted` is announced. Idempotent — archiving
     * an archived organization answers with it unchanged.
     *
     * Refused with `409 owns_products` for an organization that owns identity-provider
     * projects on this platform (a platform customer, not a tenant of your app).
     *
     * `DELETE /organizations/{id}` · action `organizations.delete` · scope `organizations:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function delete(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.delete'), [$id], [], $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * Get an organization
     *
     * `GET /organizations/{id}` · action `organizations.get` · scope `organizations:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function get(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.get'), [$id], [], $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * List organizations
     *
     * A page at a time, archived ones included. Narrow
     * with `q` (a fragment of the name or slug) or `status`.
     *
     * `GET /organizations` · action `organizations.list` · scope `organizations:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{limit?: int, after?: string, q?: string, status?: 'active'|'suspended'|'deleted'}  $query
     * @return ($options is ReturnPendingApproval ? Page<Organization>|PendingApprovalResult<Page<Organization>> : Page<Organization>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('organizations.list'), [], $query, $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * Every item of `organizations.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{limit?: int, q?: string, status?: 'active'|'suspended'|'deleted'}  $query
     * @return Generator<int, Organization, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('organizations.list'), [], $query, $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * Lift an organization's suspension so its members can sign in again.
     *
     * `POST /organizations/{id}/reactivate` · action `organizations.reactivate` · scope `organizations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function reactivate(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.reactivate'), [$id], [], $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * Suspend an organization: its members cannot sign in to it until it is reactivated.
     *
     * `POST /organizations/{id}/suspend` · action `organizations.suspend` · scope `organizations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function suspend(string $id, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.suspend'), [$id], [], $options, Value::dto(Organization::fromArray(...)));
    }

    /**
     * Make a member the organization's owner
     *
     * The new owner must be an **active** member.
     * With one current owner this is a hand-over: the previous owner stays on as `admin`.
     * An organization with no owner (created without `owner_user_id`) gets its first; one
     * with several (from before ownership was transfer-only) ends with exactly one, the
     * others stepping down to `admin`.
     *
     * Refusals: `422 not_a_member`, `409 not_active`, `409 already_owner`.
     *
     * `POST /organizations/{id}/transfer-ownership` · action `organizations.transfer_ownership` · scope `organizations:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{user_id: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Member>|PendingApprovalResult<ApiResponse<Member>> : ApiResponse<Member>)
     */
    public function transferOwnership(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.transfer_ownership'), [$id], $body, $options, Value::dto(Member::fromArray(...)));
    }

    /**
     * Rename an organization, change its slug, or replace its metadata
     *
     * A field left out is left alone; a request
     * that changes nothing answers with the organization as it is and records nothing.
     * A change is announced as the `organization.updated` webhook and recorded on the
     * organization's trail as `organization.renamed`, with the values before and after.
     *
     * `PATCH /organizations/{id}` · action `organizations.update` · scope `organizations:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name?: string, slug?: string, metadata?: array<string, mixed>|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Organization>|PendingApprovalResult<ApiResponse<Organization>> : ApiResponse<Organization>)
     */
    public function update(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('organizations.update'), [$id], $body, $options, Value::dto(Organization::fromArray(...)));
    }
}
