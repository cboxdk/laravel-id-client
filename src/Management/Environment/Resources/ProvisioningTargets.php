<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\ProvisioningTarget;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\Page;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;
use Generator;

/** `provisioning.targets.*` on the environment plane. */
class ProvisioningTargets
{
    public readonly ProvisioningTargetsStatus $status;

    public function __construct(private readonly ManagementTransport $transport)
    {
        $this->status = new ProvisioningTargetsStatus($transport);
    }

    /**
     * Register a downstream app this platform pushes an organization's people to over SCIM, or every organization's with environment_wide.
     *
     * `POST /provisioning-targets` · action `provisioning.targets.create` · scope `provisioning:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, environment_wide?: bool, name: string, base_url: string, auth_scheme: 'bearer'|'oauth2_client_credentials', secret: string, token_url?: string|null, client_id?: string|null, scope?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<ProvisioningTarget>|PendingApprovalResult<ApiResponse<ProvisioningTarget>> : ApiResponse<ProvisioningTarget>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('provisioning.targets.create'), [], $body, $options, Value::dto(ProvisioningTarget::fromArray(...)));
    }

    /**
     * Delete a downstream SCIM target. Nothing more is pushed to it.
     *
     * `DELETE /provisioning-targets/{id}` · action `provisioning.targets.delete` · scope `provisioning:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<null>|PendingApprovalResult<ApiResponse<null>> : ApiResponse<null>)
     */
    public function delete(string $id, array $body = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('provisioning.targets.delete'), [$id], $body, $options, Value::none(...));
    }

    /**
     * Read one downstream SCIM target: its URL, auth scheme, status and last error. Never its credential.
     *
     * `GET /provisioning-targets/{id}` · action `provisioning.targets.get` · scope `provisioning:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null}  $query
     * @return ($options is ReturnPendingApproval ? ApiResponse<ProvisioningTarget>|PendingApprovalResult<ApiResponse<ProvisioningTarget>> : ApiResponse<ProvisioningTarget>)
     */
    public function get(string $id, array $query = [], ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('provisioning.targets.get'), [$id], $query, $options, Value::dto(ProvisioningTarget::fromArray(...)));
    }

    /**
     * List the downstream SCIM targets people are provisioned to, optionally for one organization, with their failures. Never their credentials.
     *
     * `GET /provisioning-targets` · action `provisioning.targets.list` · scope `provisioning:read` · danger: read
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_id?: string|null, limit?: int, after?: string}  $query
     * @return ($options is ReturnPendingApproval ? Page<ProvisioningTarget>|PendingApprovalResult<Page<ProvisioningTarget>> : Page<ProvisioningTarget>)
     */
    public function list(array $query = [], ?CallOptions $options = null): Page|PendingApprovalResult
    {
        return $this->transport->page(Operations::spec('provisioning.targets.list'), [], $query, $options, Value::dto(ProvisioningTarget::fromArray(...)));
    }

    /**
     * Every item of `provisioning.targets.list`, fetching pages lazily as the iteration reaches them — `after` is followed for you. An approval is always waited on.
     *
     * @param  array{organization_id?: string|null, limit?: int}  $query
     * @return Generator<int, ProvisioningTarget, mixed, void>
     */
    public function listAll(array $query = [], ?CallOptions $options = null): Generator
    {
        return $this->transport->paginate(Operations::spec('provisioning.targets.list'), [], $query, $options, Value::dto(ProvisioningTarget::fromArray(...)));
    }
}
