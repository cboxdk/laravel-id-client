<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\SodPolicy;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `sod_policies.*` on the environment plane. */
class SodPolicies
{
    public readonly SodPoliciesStatus $status;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->status = new SodPoliciesStatus($transport);
    }

    /**
     * Define a role-conflict rule: two or more roles no one person may hold together. Enforced on every grant from now on.
     *
     * `POST /sod-policies` · action `sod_policies.create` · scope `governance:write` · danger: write
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, environment_wide?: bool, name: string, description?: string|null, role_ids: list<string>}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<SodPolicy>|PendingApprovalResult<ApiResponse<SodPolicy>> : ApiResponse<SodPolicy>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sod_policies.create'), [], $body, $options, Value::dto(SodPolicy::fromArray(...)));
    }

    /**
     * Remove a role-conflict rule. Grants it refused are allowed from then on.
     *
     * `DELETE /sod-policies/{id}` · action `sod_policies.delete` · scope `governance:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sod_policies.delete'), [$id], $body, $options, Value::none(...));
    }

    /**
     * Read one role-conflict rule: the roles no one person may hold together, and whether it is enforced.
     *
     * `GET /sod-policies/{id}` · action `sod_policies.get` · scope `governance:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<SodPolicy>|PendingApprovalResult<ApiResponse<SodPolicy>> : ApiResponse<SodPolicy>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('sod_policies.get'), [$id], $query, $options, Value::dto(SodPolicy::fromArray(...)));
    }

    /**
     * List role-conflict (segregation of duties) rules: sets of roles no one person may hold together. Optionally those binding one organization.
     *
     * `GET /sod-policies` · action `sod_policies.list` · scope `governance:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<SodPolicy>|PendingApprovalResult<Page<SodPolicy>> : Page<SodPolicy>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('sod_policies.list'), [], $query, $options, Value::dto(SodPolicy::fromArray(...)));
    }

    /**
     * Every item of `sod_policies.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, SodPolicy, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('sod_policies.list'), [], $query, $options, Value::dto(SodPolicy::fromArray(...)));
    }
}
