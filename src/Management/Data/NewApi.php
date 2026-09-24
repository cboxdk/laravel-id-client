<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/apis`.
 */
readonly class NewApi
{
    /**
     * @param  list<ApiScope>  $scopes
     */
    public function __construct(
        public string $identifier,
        public string $name,
        public array $scopes = [],
        public ?string $clientId = null,
        public ?string $organizationId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'identifier' => $this->identifier,
            'name' => $this->name,
            'scopes' => array_map(static fn (ApiScope $s): array => $s->toArray(), $this->scopes),
            'client_id' => $this->clientId,
            'organization_id' => $this->organizationId,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
