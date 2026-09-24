<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

/**
 * An app (OAuth client) registered in this environment. `clientSecret` is only on the
 * response that created an app that authenticates with one — never again.
 */
readonly class App
{
    /**
     * @param  list<string>  $redirectUris
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $grantTypes
     * @param  list<string>  $postLogoutRedirectUris
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $id,
        public string $clientId,
        public string $name,
        public ?string $type = null,
        public array $redirectUris = [],
        #[\SensitiveParameter]
        public ?string $clientSecret = null,
        public array $attributes = [],
        public ?string $clientType = null,
        public array $grantTypes = [],
        public array $postLogoutRedirectUris = [],
        public array $scopes = [],
        public bool $firstParty = false,
        public ?string $organizationId = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            clientId: Claims::requiredString($data, 'client_id'),
            name: Claims::requiredString($data, 'name'),
            type: Claims::string($data, 'type'),
            redirectUris: Claims::strings($data, 'redirect_uris'),
            clientSecret: Claims::string($data, 'client_secret'),
            attributes: $data,
            clientType: Claims::string($data, 'client_type'),
            grantTypes: Claims::strings($data, 'grant_types'),
            postLogoutRedirectUris: Claims::strings($data, 'post_logout_redirect_uris'),
            scopes: Claims::strings($data, 'scopes'),
            firstParty: Claims::bool($data, 'first_party'),
            organizationId: Claims::string($data, 'organization_id'),
            createdAt: Claims::time($data, 'created_at'),
        );
    }
}
