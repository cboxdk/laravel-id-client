<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Api` on the environment plane. */
readonly class Api implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** Absolute URI; the token's `aud`. */
        public string $identifier,
        public string $name,
        /** @var list<ApiScope> */
        public array $scopes,
        /** null when the environment owns it. */
        public ?string $organizationId = null,
        /** The app whose roles and permissions it enforces. */
        public ?string $clientId = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Api', Value::string(...)),
            identifier: Field::required($data, 'identifier', 'Api', Value::string(...)),
            name: Field::required($data, 'name', 'Api', Value::string(...)),
            scopes: Field::required($data, 'scopes', 'Api', Value::list(Value::dto(ApiScope::fromArray(...)))),
            organizationId: Field::optional($data, 'organization_id', 'Api', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'Api', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'Api', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'identifier' => $this->identifier,
            'name' => $this->name,
            'organization_id' => $this->organizationId,
            'client_id' => $this->clientId,
            'scopes' => array_map(static fn (ApiScope $item) => $item->toArray(), $this->scopes),
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
