<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `PATCH /v1/apis/{id}`. Null means "leave it"; `scopes`, when given, is the whole new
 * set, not a delta.
 */
readonly class ApiChanges
{
    /**
     * @param  list<ApiScope>|null  $scopes
     */
    public function __construct(
        public ?string $name = null,
        public ?array $scopes = null,
        public ?string $clientId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'scopes' => $this->scopes !== null ? array_map(static fn (ApiScope $s): array => $s->toArray(), $this->scopes) : null,
            'client_id' => $this->clientId,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
