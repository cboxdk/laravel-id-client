<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;

/**
 * An API (resource server) registered in this environment.
 *
 * `identifier` is the absolute URI tokens for it carry as `aud` — set it as
 * `CBOX_ID_AUDIENCE` on the API itself. `clientId` names the app whose manifest roles
 * and permissions the API enforces; `organizationId` its owner (null = the environment).
 */
readonly class Api
{
    /**
     * @param  list<ApiScope>  $scopes
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $identifier,
        public string $name,
        public ?string $organizationId = null,
        public ?string $clientId = null,
        public array $scopes = [],
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            identifier: Claims::requiredString($data, 'identifier'),
            name: Claims::requiredString($data, 'name'),
            organizationId: Claims::string($data, 'organization_id'),
            clientId: Claims::string($data, 'client_id'),
            scopes: array_map(ApiScope::fromArray(...), Claims::objects($data, 'scopes')),
            attributes: $data,
        );
    }
}
