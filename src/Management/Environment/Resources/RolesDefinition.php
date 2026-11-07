<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Resources;

use Cbox\Id\Client\Management\Environment\Operations;
use Cbox\Id\Client\Management\Environment\Schemas\Role;
use Cbox\Id\Client\Management\Transport\ApiResponse;
use Cbox\Id\Client\Management\Transport\CallOptions;
use Cbox\Id\Client\Management\Transport\ManagementTransport;
use Cbox\Id\Client\Management\Transport\PendingApprovalResult;
use Cbox\Id\Client\Management\Transport\ReturnPendingApproval;
use Cbox\Id\Client\Management\Transport\Value;

/** `roles.definition.*` on the environment plane. */
class RolesDefinition
{
    public function __construct(private readonly ManagementTransport $transport) {}

    /**
     * Activate a reviewed application role definition for its existing holders.
     *
     * `POST /roles/{id}/definition/activate` · action `roles.definition.activate` · scope `role_definitions:write` · danger: destructive
     *
     * May be held for a person's approval (`202 approval_required`): waited on, unless
     * `$options` is `CallOptions::returnPendingApproval()`.
     *
     * @param  array{version: string}  $body
     * @return ($options is ReturnPendingApproval ? ApiResponse<Role>|PendingApprovalResult<ApiResponse<Role>> : ApiResponse<Role>)
     */
    public function activate(string $id, array $body, ?CallOptions $options = null): ApiResponse|PendingApprovalResult
    {
        return $this->transport->call(Operations::spec('roles.definition.activate'), [$id], $body, $options, Value::dto(Role::fromArray(...)));
    }
}
