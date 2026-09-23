<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Concerns;

use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\ValueObjects\Actor;
use Cbox\Id\Client\ValueObjects\Organization;

/**
 * The {@see Principal} questions, answered from a claim set.
 *
 * Shared by everything that carries Cbox ID's claims, so a sign-in, a bearer token and
 * the session read `org_role`, `roles`, `permissions` and `act` identically. Three copies
 * would be three chances for one of them to start granting on a malformed claim.
 *
 * @property-read array<string, mixed> $claims
 */
trait ReadsAuthorizationClaims
{
    public function organization(): ?Organization
    {
        return Organization::fromClaims($this->claims);
    }

    /**
     * Every organization the person belongs to — the `organizations` claim, present only
     * when the login asked for the `organizations` scope. Powers a switcher.
     *
     * @return list<Organization>
     */
    public function organizations(): array
    {
        $out = [];

        foreach (Claims::objects($this->claims, 'organizations') as $entry) {
            $organization = Organization::fromListEntry($entry);

            if ($organization !== null) {
                $out[] = $organization;
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return Claims::strings($this->claims, 'roles');
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return Claims::strings($this->claims, 'permissions');
    }

    /**
     * Exact match only. Cbox ID resolves roles to permissions before it signs anything,
     * so there is no wildcard to expand here — and inventing one client-side would grant
     * what the issuer never did.
     */
    public function hasPermission(string $permission): bool
    {
        return $permission !== '' && in_array($permission, $this->permissions(), true);
    }

    /** @param  iterable<string>  $permissions */
    public function hasAnyPermission(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /** @param  iterable<string>  $permissions */
    public function hasAllPermissions(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    public function hasRole(string $role): bool
    {
        return $role !== '' && in_array($role, $this->roles(), true);
    }

    public function actor(): ?Actor
    {
        return Actor::fromClaims($this->claims);
    }

    public function isSupportSession(): bool
    {
        return $this->actor() !== null;
    }
}
