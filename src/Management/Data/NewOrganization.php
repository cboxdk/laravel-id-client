<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/organizations`. Name `ownerUserId` to create the organization WITH its Owner
 * in one call — an organization nobody owns is one nobody can administer.
 */
readonly class NewOrganization
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $parentId = null,
        public ?string $ownerUserId = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parentId,
            'owner_user_id' => $this->ownerUserId,
        ], static fn (?string $v): bool => $v !== null);
    }
}
