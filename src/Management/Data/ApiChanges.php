<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use InvalidArgumentException;

/**
 * `PATCH /v1/apis/{id}`. A field left null is left alone.
 *
 * `scopes`, when given, is the COMPLETE set afterwards: scopes named are added or
 * updated, scopes left out are REMOVED. All or nothing. To unlink the app the API
 * enforces, pass `unlinkClient: true` — that sends an explicit `"client_id": null`, which
 * a plain null here could not (it means "leave it").
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
        public bool $unlinkClient = false,
    ) {
        if ($unlinkClient && $clientId !== null) {
            throw new InvalidArgumentException('ApiChanges: set clientId or unlinkClient, not both.');
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $body = array_filter([
            'name' => $this->name,
            'scopes' => $this->scopes !== null ? array_map(static fn (ApiScope $s): array => $s->toArray(), $this->scopes) : null,
            'client_id' => $this->clientId,
        ], static fn (mixed $v): bool => $v !== null);

        if ($this->unlinkClient) {
            $body['client_id'] = null;
        }

        return $body;
    }
}
