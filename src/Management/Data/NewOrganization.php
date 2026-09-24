<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/organizations`. Name `ownerUserId` to create the organization WITH its Owner
 * in one call — an organization nobody owns is one nobody can administer. `slug` is
 * derived from the name when left out; send your own if you retry creates, so a retry is
 * a recognisable `422 slug_taken` rather than a second organization. `type` is
 * `customer` (default) or `reseller`.
 */
readonly class NewOrganization
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $parentId = null,
        public ?string $ownerUserId = null,
        public ?string $type = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parentId,
            'owner_user_id' => $this->ownerUserId,
            'type' => $this->type,
        ], static fn (?string $v): bool => $v !== null);
    }
}
