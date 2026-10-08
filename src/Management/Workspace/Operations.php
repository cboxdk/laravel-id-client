<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace;

use Cbox\Id\Client\Management\Transport\Danger;
use Cbox\Id\Client\Management\Transport\OperationSpec;
use Cbox\Id\Client\Management\Transport\Pagination;
use InvalidArgumentException;

/**
 * Every operation of the Cbox ID — Workspace Management API — method, path, scope, danger and whether it can be
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
            'environments.create',
            'environments.domain.remove',
            'environments.domain.request',
            'environments.domain.verify',
            'environments.list',
            'keys.environment.create',
            'keys.environment.revoke',
            'keys.workspace.create',
            'keys.workspace.list',
            'keys.workspace.revoke',
            'projects.create',
            'projects.list',
            'projects.reactivate',
            'projects.rename',
            'projects.suspend',
            'projects.verification.resend',
            'team.environment_access',
            'team.invitations.list',
            'team.invitations.resend',
            'team.invitations.revoke',
            'team.invite',
            'team.list',
            'team.remove',
            'team.role',
            'team.transfer_ownership',
            'workspace.get',
            'workspace.settings.update',
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
            'action_approvals.get' => new OperationSpec(action: null, operationId: null, method: 'GET', path: '/workspace/action-approvals/{id}', pathParams: ['id'], scope: null, danger: null, approval: false, body: false, pagination: null),
            'environments.create' => new OperationSpec(action: 'environments.create', operationId: 'environments_create', method: 'POST', path: '/workspace/environments', pathParams: [], scope: 'environments:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'environments.domain.remove' => new OperationSpec(action: 'environments.domain.remove', operationId: 'environments_domain_remove', method: 'DELETE', path: '/workspace/environments/{environment_id}/domain', pathParams: ['environment_id'], scope: 'environments:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'environments.domain.request' => new OperationSpec(action: 'environments.domain.request', operationId: 'environments_domain_request', method: 'POST', path: '/workspace/environments/{environment_id}/domain', pathParams: ['environment_id'], scope: 'environments:write', danger: Danger::Write, approval: true, body: true, pagination: null),
            'environments.domain.verify' => new OperationSpec(action: 'environments.domain.verify', operationId: 'environments_domain_verify', method: 'POST', path: '/workspace/environments/{environment_id}/domain/verify', pathParams: ['environment_id'], scope: 'environments:write', danger: Danger::Write, approval: true, body: false, pagination: null),
            'environments.list' => new OperationSpec(action: 'environments.list', operationId: 'environments_list', method: 'GET', path: '/workspace/environments', pathParams: [], scope: 'workspace:read', danger: null, approval: true, body: false, pagination: Pagination::Page),
            'keys.environment.create' => new OperationSpec(action: 'keys.environment.create', operationId: 'keys_environment_create', method: 'POST', path: '/workspace/environments/{environment_id}/keys', pathParams: ['environment_id'], scope: 'keys:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'keys.environment.revoke' => new OperationSpec(action: 'keys.environment.revoke', operationId: 'keys_environment_revoke', method: 'DELETE', path: '/workspace/environments/{environment_id}/keys/{id}', pathParams: ['environment_id', 'id'], scope: 'keys:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'keys.workspace.create' => new OperationSpec(action: 'keys.workspace.create', operationId: 'keys_workspace_create', method: 'POST', path: '/workspace/keys', pathParams: [], scope: 'keys:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'keys.workspace.list' => new OperationSpec(action: 'keys.workspace.list', operationId: 'keys_workspace_list', method: 'GET', path: '/workspace/keys', pathParams: [], scope: 'workspace:read', danger: Danger::Read, approval: true, body: false, pagination: Pagination::Page),
            'keys.workspace.revoke' => new OperationSpec(action: 'keys.workspace.revoke', operationId: 'keys_workspace_revoke', method: 'DELETE', path: '/workspace/keys/{id}', pathParams: ['id'], scope: 'keys:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'projects.create' => new OperationSpec(action: 'projects.create', operationId: 'projects_create', method: 'POST', path: '/workspace/projects', pathParams: [], scope: 'projects:write', danger: null, approval: true, body: true, pagination: null),
            'projects.list' => new OperationSpec(action: 'projects.list', operationId: 'projects_list', method: 'GET', path: '/workspace/projects', pathParams: [], scope: 'workspace:read', danger: null, approval: true, body: false, pagination: null),
            'projects.reactivate' => new OperationSpec(action: 'projects.reactivate', operationId: 'projects_reactivate', method: 'POST', path: '/workspace/projects/{id}/reactivate', pathParams: ['id'], scope: 'projects:write', danger: Danger::Write, approval: true, body: false, pagination: null),
            'projects.rename' => new OperationSpec(action: 'projects.rename', operationId: 'projects_rename', method: 'PATCH', path: '/workspace/projects/{id}', pathParams: ['id'], scope: 'projects:write', danger: Danger::Write, approval: true, body: true, pagination: null),
            'projects.suspend' => new OperationSpec(action: 'projects.suspend', operationId: 'projects_suspend', method: 'POST', path: '/workspace/projects/{id}/suspend', pathParams: ['id'], scope: 'projects:write', danger: Danger::Write, approval: true, body: false, pagination: null),
            'projects.verification.resend' => new OperationSpec(action: 'projects.verification.resend', operationId: 'projects_verification_resend', method: 'POST', path: '/workspace/projects/verification/resend', pathParams: [], scope: 'projects:write', danger: Danger::Write, approval: true, body: false, pagination: null),
            'team.environment_access' => new OperationSpec(action: 'team.environment_access', operationId: 'team_environment_access', method: 'PUT', path: '/workspace/members/{id}/access', pathParams: ['id'], scope: 'team:write', danger: Danger::Write, approval: true, body: true, pagination: null),
            'team.invitations.list' => new OperationSpec(action: 'team.invitations.list', operationId: 'team_invitations_list', method: 'GET', path: '/workspace/invitations', pathParams: [], scope: 'team:read', danger: null, approval: true, body: false, pagination: null),
            'team.invitations.resend' => new OperationSpec(action: 'team.invitations.resend', operationId: 'team_invitations_resend', method: 'POST', path: '/workspace/invitations/{id}/resend', pathParams: ['id'], scope: 'team:write', danger: null, approval: true, body: false, pagination: null),
            'team.invitations.revoke' => new OperationSpec(action: 'team.invitations.revoke', operationId: 'team_invitations_revoke', method: 'DELETE', path: '/workspace/invitations/{id}', pathParams: ['id'], scope: 'team:write', danger: null, approval: true, body: false, pagination: null),
            'team.invite' => new OperationSpec(action: 'team.invite', operationId: 'team_invite', method: 'POST', path: '/workspace/members', pathParams: [], scope: 'team:write', danger: null, approval: true, body: true, pagination: null),
            'team.list' => new OperationSpec(action: 'team.list', operationId: 'team_list', method: 'GET', path: '/workspace/members', pathParams: [], scope: 'team:read', danger: null, approval: true, body: false, pagination: Pagination::Page),
            'team.remove' => new OperationSpec(action: 'team.remove', operationId: 'team_remove', method: 'DELETE', path: '/workspace/members/{id}', pathParams: ['id'], scope: 'team:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'team.role' => new OperationSpec(action: 'team.role', operationId: 'team_role', method: 'PATCH', path: '/workspace/members/{id}/role', pathParams: ['id'], scope: 'team:write', danger: Danger::Write, approval: true, body: true, pagination: null),
            'team.transfer_ownership' => new OperationSpec(action: 'team.transfer_ownership', operationId: 'team_transfer_ownership', method: 'POST', path: '/workspace/members/{id}/transfer-ownership', pathParams: ['id'], scope: 'team:write', danger: Danger::Critical, approval: true, body: false, pagination: null),
            'workspace.get' => new OperationSpec(action: 'workspace.get', operationId: 'workspace_get', method: 'GET', path: '/workspace', pathParams: [], scope: 'workspace:read', danger: null, approval: true, body: false, pagination: null),
            'workspace.settings.update' => new OperationSpec(action: 'workspace.settings.update', operationId: 'workspace_settings_update', method: 'PATCH', path: '/workspace', pathParams: [], scope: 'settings:write', danger: Danger::Write, approval: true, body: true, pagination: null),
            default => throw new InvalidArgumentException("No workspace operation is keyed {$key}."),
        };
    }
}
