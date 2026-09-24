<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Tenancy;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lets Laravel's own authorization answer Cbox ID permissions.
 *
 * With it on, an ability shaped like a manifest permission — `feature:action`, e.g.
 * `invoices:create` — is granted when the current principal holds that permission, so
 * `@can('invoices:create')`, `$user->can('invoices:create')`, `Gate::authorize(…)` and
 * `$this->authorize(…)` all work without a policy per permission.
 *
 * **IT ONLY EVER GRANTS.** A missing permission returns null, not false, so a Gate or
 * policy your application defines for the same ability still gets its say, and an
 * ability that is not permission-shaped (`update`, `view-dashboard`) is never touched.
 * A held permission is answered true before your definitions run — that is the point of
 * the permission — so keep row-level checks ("is this invoice in their organization?")
 * in a policy on a differently-named ability.
 *
 * Opt-in (`cbox-id-client.authorization.gate`), because a before-callback runs for every
 * check in the application and nobody should get one they did not ask for.
 */
class PermissionGate
{
    /**
     * `feature:action`, the manifest's permission shape. Letters, digits, `.`, `_` and
     * `-` on both sides of exactly one colon.
     */
    public const PATTERN = '/^[A-Za-z0-9._-]+:[A-Za-z0-9._-]+$/';

    public static function isPermission(string $ability): bool
    {
        return preg_match(self::PATTERN, $ability) === 1;
    }

    public static function register(Gate $gate, CurrentPrincipal $principals): void
    {
        $gate->before(static function (?Authenticatable $user, string $ability) use ($principals): ?bool {
            if (! self::isPermission($ability)) {
                return null;
            }

            $principal = $principals->resolve($user, request());

            return $principal !== null && $principal->hasPermission($ability) ? true : null;
        });
    }
}
