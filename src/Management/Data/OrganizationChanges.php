<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `PATCH /v1/organizations/{id}`. Only what is set is sent; null means "leave it".
 */
readonly class OrganizationChanges
{
    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'slug' => $this->slug,
        ], static fn (?string $v): bool => $v !== null);
    }
}
