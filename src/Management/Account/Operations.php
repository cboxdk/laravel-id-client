<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account;

use Cbox\Id\Client\Management\Transport\Danger;
use Cbox\Id\Client\Management\Transport\OperationSpec;
use InvalidArgumentException;

/**
 * Every operation of the Cbox ID — My Account API — method, path, scope, danger and whether it can be
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
            'account.api_keys.create',
            'account.api_keys.revoke',
            'account.applications.revoke',
            'account.devices.remove',
            'account.mfa.sms.remove',
            'account.organizations.leave',
            'account.passkeys.remove',
            'account.pipes.disconnect',
            'account.profile.update',
            'account.sessions.revoke',
            'account.sessions.revoke_others',
            'account.social.unlink',
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
            'action_approvals.get' => new OperationSpec(action: null, operationId: null, method: 'GET', path: '/me/action-approvals/{id}', pathParams: ['id'], scope: null, danger: null, approval: false, body: false, pagination: null),
            'account.api_keys.create' => new OperationSpec(action: 'account.api_keys.create', operationId: 'account_api_keys_create', method: 'POST', path: '/me/organizations/{organization_id}/api-keys', pathParams: ['organization_id'], scope: 'account:api_keys:write', danger: Danger::Critical, approval: true, body: true, pagination: null),
            'account.api_keys.revoke' => new OperationSpec(action: 'account.api_keys.revoke', operationId: 'account_api_keys_revoke', method: 'DELETE', path: '/me/organizations/{organization_id}/api-keys/{key_id}', pathParams: ['organization_id', 'key_id'], scope: 'account:api_keys:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'account.applications.revoke' => new OperationSpec(action: 'account.applications.revoke', operationId: 'account_applications_revoke', method: 'DELETE', path: '/me/applications/{client_id}', pathParams: ['client_id'], scope: 'account:applications:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'account.devices.remove' => new OperationSpec(action: 'account.devices.remove', operationId: 'account_devices_remove', method: 'DELETE', path: '/me/devices/{device_id}', pathParams: ['device_id'], scope: 'account:devices:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'account.mfa.sms.remove' => new OperationSpec(action: 'account.mfa.sms.remove', operationId: 'account_mfa_sms_remove', method: 'DELETE', path: '/me/mfa/sms', pathParams: [], scope: 'account:sign_in:write', danger: Danger::Critical, approval: true, body: false, pagination: null),
            'account.organizations.leave' => new OperationSpec(action: 'account.organizations.leave', operationId: 'account_organizations_leave', method: 'POST', path: '/me/organizations/{organization_id}/leave', pathParams: ['organization_id'], scope: 'account:organizations:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'account.passkeys.remove' => new OperationSpec(action: 'account.passkeys.remove', operationId: 'account_passkeys_remove', method: 'DELETE', path: '/me/passkeys/{passkey_id}', pathParams: ['passkey_id'], scope: 'account:sign_in:write', danger: Danger::Critical, approval: true, body: false, pagination: null),
            'account.pipes.disconnect' => new OperationSpec(action: 'account.pipes.disconnect', operationId: 'account_pipes_disconnect', method: 'DELETE', path: '/me/pipes/{provider}', pathParams: ['provider'], scope: 'account:pipes:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'account.profile.update' => new OperationSpec(action: 'account.profile.update', operationId: 'account_profile_update', method: 'PATCH', path: '/me/profile', pathParams: [], scope: 'account:profile:write', danger: Danger::Write, approval: true, body: true, pagination: null),
            'account.sessions.revoke' => new OperationSpec(action: 'account.sessions.revoke', operationId: 'account_sessions_revoke', method: 'DELETE', path: '/me/sessions/{session_id}', pathParams: ['session_id'], scope: 'account:sessions:write', danger: Danger::Destructive, approval: true, body: false, pagination: null),
            'account.sessions.revoke_others' => new OperationSpec(action: 'account.sessions.revoke_others', operationId: 'account_sessions_revoke_others', method: 'POST', path: '/me/sessions/revoke-others', pathParams: [], scope: 'account:sessions:write', danger: Danger::Critical, approval: true, body: false, pagination: null),
            'account.social.unlink' => new OperationSpec(action: 'account.social.unlink', operationId: 'account_social_unlink', method: 'DELETE', path: '/me/social/{provider}', pathParams: ['provider'], scope: 'account:sign_in:write', danger: Danger::Critical, approval: true, body: false, pagination: null),
            default => throw new InvalidArgumentException("No account operation is keyed {$key}."),
        };
    }
}
