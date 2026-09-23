<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Enums;

/**
 * A member's tier in an organization — the `org_role` claim.
 *
 * Mirrors Cbox ID's own membership roles, which are strictly ordered: Owner > Admin >
 * Developer > Member > Viewer. This is the coarse, built-in tier every organization has;
 * the fine-grained `roles`/`permissions` your application declares in its manifest are a
 * separate axis and arrive in their own claims.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Developer = 'developer';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Developer => 'Developer',
            self::Member => 'Member',
            self::Viewer => 'Viewer',
        };
    }

    /** Higher is more privileged. Only meaningful for comparing two tiers. */
    public function weight(): int
    {
        return match ($this) {
            self::Owner => 50,
            self::Admin => 40,
            self::Developer => 30,
            self::Member => 20,
            self::Viewer => 10,
        };
    }

    /** Whether this tier is `$minimum` or above — `cbox-id.org:admin` asks this. */
    public function atLeast(self $minimum): bool
    {
        return $this->weight() >= $minimum->weight();
    }

    /**
     * Manage the organization itself — members, invitations, settings. Owner and Admin
     * only, as on Cbox ID: a Developer is a technical role, not an administrative one.
     */
    public function canManageOrganization(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /** Everyone but the read-only Viewer. */
    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }
}
