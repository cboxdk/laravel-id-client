<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Enums;

use Cbox\Id\Client\Contracts\Management;

/**
 * The tiers the management API hands out: `admin` or `member`.
 *
 * Narrower than {@see OrganizationRole} on purpose. `owner` is never assigned — ownership
 * moves with {@see Management::transferOwnership()} — and a type
 * that cannot hold it makes "add them as owner" a compile error rather than a 422.
 */
enum AssignableMemberRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    public function toOrganizationRole(): OrganizationRole
    {
        return OrganizationRole::from($this->value);
    }
}
