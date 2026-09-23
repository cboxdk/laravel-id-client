<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;

/**
 * An app (OAuth client) registered in this environment. The secret is only ever in
 * `clientSecret` on the response that created it; it is not retrievable afterwards.
 */
readonly class App
{
    /**
     * @param  list<string>  $redirectUris
     * @param  array<string, mixed>  $attributes
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
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $id = Claims::requiredString($data, 'id');

        return new self(
            id: $id,
            clientId: Claims::string($data, 'client_id') ?? $id,
            name: Claims::requiredString($data, 'name'),
            type: Claims::string($data, 'type'),
            redirectUris: Claims::strings($data, 'redirect_uris'),
            clientSecret: Claims::string($data, 'client_secret'),
            attributes: $data,
        );
    }
}
