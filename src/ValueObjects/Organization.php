<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Support\Claims;

/**
 * The organization a token or a sign-in acts for — `org`, `org_name`, `org_role`.
 *
 * `role` is the member's built-in tier there, and null when the instance did not say
 * (an older Cbox ID that predates `org_role`, or a tier this SDK does not know yet). A
 * null role grants nothing: every check on it treats unknown as "not enough".
 */
readonly class Organization
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?OrganizationRole $role = null,
    ) {}

    /**
     * From a token's top-level claims, or null when it acts for no organization.
     *
     * @param  array<array-key, mixed>  $claims
     */
    public static function fromClaims(array $claims): ?self
    {
        $id = Claims::string($claims, 'org');

        if ($id === null) {
            return null;
        }

        $role = Claims::string($claims, 'org_role');

        return new self($id, Claims::string($claims, 'org_name'), $role !== null ? OrganizationRole::tryFrom($role) : null);
    }

    /**
     * One entry of the `organizations` claim (`{id, name, role}`), or null when malformed.
     *
     * @param  array<array-key, mixed>  $entry
     */
    public static function fromListEntry(array $entry): ?self
    {
        $id = Claims::string($entry, 'id');

        if ($id === null) {
            return null;
        }

        $role = Claims::string($entry, 'role');

        return new self($id, Claims::string($entry, 'name'), $role !== null ? OrganizationRole::tryFrom($role) : null);
    }

    public function isOwner(): bool
    {
        return $this->role === OrganizationRole::Owner;
    }

    /** Owner or Admin — may manage members, invitations and settings. */
    public function canManage(): bool
    {
        return $this->role?->canManageOrganization() ?? false;
    }

    public function roleAtLeast(OrganizationRole $minimum): bool
    {
        return $this->role?->atLeast($minimum) ?? false;
    }

    /** The label to show: the name, or the id when the instance sent none. */
    public function label(): string
    {
        return $this->name ?? $this->id;
    }
}
