<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

/**
 * Cbox ID's scope names, old and new.
 *
 * Cbox ID is renaming its scopes to `id:<resource>:<read|write>` (`app_catalog:resolve` is
 * `id:app-catalog-for-user:read`, `apps.manifest` is `id:app-manifests:write`). For one
 * release a token or grant may carry either spelling, so where the SDK reads a granted
 * scope it asks {@see self::holds()} rather than `in_array()`.
 *
 * The SDK still REQUESTS the old names: every Cbox ID accepts them, and a new one answers
 * with the new name in the granted scope. Scopes that are not Cbox ID's own (OIDC's
 * `openid`, the scopes a customer defines on its APIs) are unaffected.
 */
final class Scopes
{
    /**
     * Old name => the new name(s) it stands for. Mirrors Cbox ID's scope registry.
     *
     * @var array<string, list<string>>
     */
    public const array RENAMED = [
        'account:api_keys:write' => ['id:my-api-keys:write'],
        'account:applications:write' => ['id:my-apps:write'],
        'account:apps:read' => ['id:my-apps:read'],
        'account:devices:write' => ['id:my-devices:write'],
        'account:organizations:write' => ['id:my-organizations:write'],
        'account:pipes:write' => ['id:my-pipes:write'],
        'account:profile:write' => ['id:my-profile:write'],
        'account:sessions:write' => ['id:my-sessions:write'],
        'account:sign_in:write' => ['id:my-sign-in:write'],
        'account:waitlists:write' => ['id:my-waitlists:write'],
        'api_keys:read' => ['id:api-keys:read'],
        'api_keys:write' => ['id:api-keys:write'],
        'apis:read' => ['id:apis:read'],
        'apis:write' => ['id:apis:write'],
        'app_catalog:read' => ['id:app-catalog:read'],
        'app_catalog:resolve' => ['id:app-catalog-for-user:read'],
        'app_catalog:write' => ['id:app-catalog:write'],
        'approvals.read' => ['id:approvals:read'],
        'approvals.write' => ['id:approvals:write'],
        'approvals:read' => ['id:approvals:read'],
        'approvals:write' => ['id:approvals:write'],
        'apps.manifest' => ['id:app-manifests:write'],
        'apps:read' => ['id:apps:read'],
        'apps:write' => ['id:apps:write'],
        'audit:read' => ['id:audit-events:read'],
        'audit_logs:export' => ['id:audit-log-exports:write'],
        'audit_logs:manage' => ['id:audit-log-schemas:write'],
        'audit_logs:read' => ['id:audit-logs:read'],
        'audit_logs:write' => ['id:audit-logs:write'],
        'billing:write' => ['id:billing:write'],
        'branding:read' => ['id:branding:read'],
        'branding:write' => ['id:branding:write'],
        'devices.manage' => ['id:devices:write'],
        'directory_sync:read' => ['id:directories:read'],
        'directory_sync:write' => ['id:directories:write'],
        'domains:read' => ['id:domains:read'],
        'domains:write' => ['id:domains:write'],
        'environments:write' => ['id:environments:write'],
        'events:read' => ['id:events:read'],
        'feature_flags:read' => ['id:feature-flags:read'],
        'feature_flags:write' => ['id:feature-flags:write'],
        'fga:read' => ['id:fga:read'],
        'fga:schema' => ['id:fga-schema:write'],
        'fga:write' => ['id:fga:write'],
        'frontend_keys:read' => ['id:frontend-keys:read'],
        'frontend_keys:write' => ['id:frontend-keys:write'],
        'governance:read' => ['id:governance:read'],
        'governance:write' => ['id:governance:write'],
        'hooks:read' => ['id:hooks:read'],
        'hooks:write' => ['id:hooks:write'],
        'invitations:read' => ['id:invitations:read'],
        'invitations:write' => ['id:invitations:write'],
        'keys:read' => ['id:environment-keys:read'],
        'keys:write' => ['id:environment-keys:write', 'id:organization-keys:write'],
        'log_streams:read' => ['id:log-streams:read'],
        'log_streams:write' => ['id:log-streams:write'],
        'members:read' => ['id:organization-memberships:read'],
        'members:write' => ['id:organization-memberships:write'],
        'operator:environments:write' => ['id:admin-environments:write'],
        'operator:operators:write' => ['id:admin-operators:write'],
        'operator:organizations:write' => ['id:admin-tenant-organizations:write'],
        'operator:workspaces:write' => ['id:admin-organizations:write'],
        'organizations:read' => ['id:organizations:read'],
        'organizations:write' => ['id:organizations:write'],
        'pipes:read' => ['id:pipes:read'],
        'pipes:write' => ['id:pipes:write'],
        'portal_links:read' => ['id:portal-links:read'],
        'portal_links:write' => ['id:portal-links:write'],
        'projects:write' => ['id:projects:write'],
        'provisioning:read' => ['id:provisioning-targets:read'],
        'provisioning:write' => ['id:provisioning-targets:write'],
        'radar:manage' => ['id:radar-settings:write'],
        'radar:read' => ['id:radar:read'],
        'radar:write' => ['id:radar:write'],
        'role_definitions:write' => ['id:role-definitions:write'],
        'roles:read' => ['id:roles:read'],
        'roles:write' => ['id:roles:write'],
        'saml_apps:read' => ['id:saml-apps:read'],
        'saml_apps:write' => ['id:saml-apps:write'],
        'service_connections.inspect' => ['id:service-connections:read'],
        'service_connections.lease' => ['id:service-connection-leases:write'],
        'service_connections.manage' => ['id:service-connections:write'],
        'settings:write' => ['id:organization:write'],
        'signals:events:read' => ['id:signals-events:read'],
        'signals_source:read' => ['id:signals-source:read'],
        'signin:read' => ['id:sign-in:read'],
        'signin:write' => ['id:sign-in:write'],
        'sso:read' => ['id:sso-connections:read'],
        'sso:write' => ['id:sso-connections:write'],
        'support:write' => ['id:support-sessions:write'],
        'team:read' => ['id:members:read'],
        'team:write' => ['id:members:write'],
        'token_vault:read' => ['id:vault-secrets:read'],
        'token_vault:write' => ['id:vault-secrets:write'],
        'user_property_namespaces:read' => ['id:user-property-namespaces:read'],
        'user_property_namespaces:write' => ['id:user-property-namespaces:write'],
        'users:erase' => ['id:user-erasures:write'],
        'users:read' => ['id:users:read'],
        'users:write' => ['id:users:write'],
        'vault.lease' => ['id:vault-leases:write'],
        'vault.manage' => ['id:vault-secrets:write'],
        'webhooks:read' => ['id:webhooks:read'],
        'webhooks:write' => ['id:webhooks:write'],
        'workspace:read' => ['id:organization:read'],
    ];

    /** @var array<string, list<string>>|null new name => the old names that stand for it */
    private static ?array $legacy = null;

    /**
     * Every spelling that stands for `$scope`: itself, its new name(s) when it is old, and
     * its old names when it is new. Unknown scopes stand for themselves only.
     *
     * @return list<string>
     */
    public static function equivalents(string $scope): array
    {
        return array_values(array_unique([$scope, ...(self::RENAMED[$scope] ?? []), ...(self::legacyMap()[$scope] ?? [])]));
    }

    /**
     * Whether a list of granted scopes holds `$required`, in either spelling.
     *
     * @param  iterable<mixed>  $held
     */
    public static function holds(iterable $held, string $required): bool
    {
        $names = self::equivalents($required);

        foreach ($held as $scope) {
            if (is_string($scope) && in_array($scope, $names, true)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, list<string>> */
    private static function legacyMap(): array
    {
        if (self::$legacy === null) {
            $legacy = [];

            foreach (self::RENAMED as $old => $new) {
                foreach ($new as $name) {
                    $legacy[$name][] = $old;
                }
            }

            self::$legacy = $legacy;
        }

        return self::$legacy;
    }
}
