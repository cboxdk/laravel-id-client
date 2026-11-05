<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Authz;

use Cbox\Id\Client\Enums\OrganizationRole;

/** A cheap, default-denied value object for consumers and test doubles. */
readonly class PermissionDecision
{
    /** @param array<string, bool> $permissions */
    public function __construct(
        public string $subject = '',
        public ?string $organization = null,
        public string $clientId = '',
        public ?OrganizationRole $organizationRole = null,
        public bool $organizationActive = false,
        public array $permissions = [],
    ) {}

    public function allows(string $permission): bool
    {
        return $this->organizationActive && ($this->permissions[$permission] ?? false);
    }

    public function allowed(): bool
    {
        return $this->organizationActive && $this->permissions !== [] && ! in_array(false, $this->permissions, true);
    }
}
