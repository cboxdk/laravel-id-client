<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;

/**
 * A scope an API defines. `tenantRequestable: false` keeps it off apps your customers'
 * organizations own — only environment-owned apps can be granted it.
 */
readonly class ApiScope
{
    public function __construct(
        public string $key,
        public ?string $description = null,
        public bool $tenantRequestable = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Claims::requiredString($data, 'key'),
            Claims::string($data, 'description'),
            Claims::bool($data, 'tenant_requestable', true),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'key' => $this->key,
            'description' => $this->description,
            'tenant_requestable' => $this->tenantRequestable,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
