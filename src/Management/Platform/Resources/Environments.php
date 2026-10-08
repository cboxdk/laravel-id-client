<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Resources;

use Cbox\Id\Client\Management\Platform\Operations;
use Cbox\Id\Client\Management\Platform\Schemas\PlatformEnvironment;
use Cbox\Id\Client\Management\Platform\Schemas\ProvisionedEnvironment;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `environments.*` on the platform plane. */
class Environments
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Create an environment on the deployment, with its own signing key. A domain is verified separately, by DNS.
     *
     * Requires scope `operator:environments:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `POST /platform/environments` · action `platform.environments.create` · scope `operator:environments:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{name: string, domain?: string|null}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<PlatformEnvironment>|PendingApprovalResult<ApiResponse<PlatformEnvironment>> : ApiResponse<PlatformEnvironment>)
     */
    public function create(array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.environments.create'), [], $body, $options, Value::dto(PlatformEnvironment::fromArray(...)));
    }

    /**
     * Bootstrap an environment with its first organization and an owner administrator, so people can sign in to it.
     *
     * Requires scope `operator:environments:write` on an access token delegated by an active platform operator. No management key is accepted.
     *
     * `POST /platform/environments/{environment_id}/provision` · action `platform.environments.provision` · scope `operator:environments:write` · danger: critical
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{organization_name: string, admin_name: string, admin_email: string, admin_password: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<ProvisionedEnvironment>|PendingApprovalResult<ApiResponse<ProvisionedEnvironment>> : ApiResponse<ProvisionedEnvironment>)
     */
    public function provision(string $environmentId, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('platform.environments.provision'), [$environmentId], $body, $options, Value::dto(ProvisionedEnvironment::fromArray(...)));
    }
}
