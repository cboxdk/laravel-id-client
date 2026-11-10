<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Shell;

use Cbox\Id\Client\Enums\OrganizationRole;

/**
 * One of the person's organizations as the app keeps it locally: the Cbox ID id, the
 * slug the URL carries (`/orgs/{slug}`, the same in every app) and the name.
 */
readonly class MirroredOrganization
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?OrganizationRole $role = null,
    ) {}
}
