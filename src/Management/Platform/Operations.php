<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform;

use Cbox\Id\Client\Management\Transport\Danger;
use Cbox\Id\Client\Management\Transport\OperationSpec;
use InvalidArgumentException;

/**
 * Every operation of the Cbox ID — Operator API — method, path, scope, danger and whether it can be
 * held for approval — keyed by action name.
 */
class Operations
{
    /** @var array<string, OperationSpec> */
    private static array $specs = [];

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            'action_approvals.get',
            'platform.environments.create',
            'platform.environments.provision',
            'platform.operators.create',
            'platform.operators.set_status',
            'platform.organizations.create',
            'platform.organizations.move',
            'platform.organizations.set_status',
            'platform.workspaces.create',
            'platform.workspaces.set_status',
        ];
    }

    /** @return array<string, OperationSpec> */
    public static function all(): array
    {
        $all = [];

        foreach (self::keys() as $key) {
            $all[$key] = self::spec($key);
        }

        return $all;
    }

    public static function spec(string $key): OperationSpec
    {
        return self::$specs[$key] ??= match ($key) {
            'action_approvals.get' => new OperationSpec(action: null, operationId: null, method: 'GET', path: '/platform/action-approvals/{id}', pathParams: ['id'], scope: null, danger: null, approval: false, body: false, pagination: null),
            'platform.environments.create' => new OperationSpec(action: 'platform.environments.create', operationId: 'platform_environments_create', method: 'POST', path: '/platform/environments', pathParams: [], scope: 'operator:environments:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.environments.provision' => new OperationSpec(action: 'platform.environments.provision', operationId: 'platform_environments_provision', method: 'POST', path: '/platform/environments/{environment_id}/provision', pathParams: ['environment_id'], scope: 'operator:environments:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.operators.create' => new OperationSpec(action: 'platform.operators.create', operationId: 'platform_operators_create', method: 'POST', path: '/platform/operators', pathParams: [], scope: 'operator:operators:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.operators.set_status' => new OperationSpec(action: 'platform.operators.set_status', operationId: 'platform_operators_set_status', method: 'PUT', path: '/platform/operators/{operator_id}/status', pathParams: ['operator_id'], scope: 'operator:operators:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.organizations.create' => new OperationSpec(action: 'platform.organizations.create', operationId: 'platform_organizations_create', method: 'POST', path: '/platform/environments/{environment_id}/organizations', pathParams: ['environment_id'], scope: 'operator:organizations:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.organizations.move' => new OperationSpec(action: 'platform.organizations.move', operationId: 'platform_organizations_move', method: 'PUT', path: '/platform/environments/{environment_id}/organizations/{organization_id}/parent', pathParams: ['environment_id', 'organization_id'], scope: 'operator:organizations:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.organizations.set_status' => new OperationSpec(action: 'platform.organizations.set_status', operationId: 'platform_organizations_set_status', method: 'PUT', path: '/platform/environments/{environment_id}/organizations/{organization_id}/status', pathParams: ['environment_id', 'organization_id'], scope: 'operator:organizations:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.workspaces.create' => new OperationSpec(action: 'platform.workspaces.create', operationId: 'platform_workspaces_create', method: 'POST', path: '/platform/workspaces', pathParams: [], scope: 'operator:workspaces:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'platform.workspaces.set_status' => new OperationSpec(action: 'platform.workspaces.set_status', operationId: 'platform_workspaces_set_status', method: 'PUT', path: '/platform/workspaces/{workspace_id}/status', pathParams: ['workspace_id'], scope: 'operator:workspaces:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            default => throw new InvalidArgumentException("No platform operation is keyed {$key}."),
        };
    }
}
