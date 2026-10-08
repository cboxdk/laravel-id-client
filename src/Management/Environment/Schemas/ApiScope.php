<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/ApiScope` on the environment plane. */
readonly class ApiScope implements JsonSerializable
{
    public function __construct(
        public string $key,
        public ?string $description = null,
        /** Whether apps an organization owns may be granted it; false keeps it for the environment's own apps. */
        public ?bool $tenantRequestable = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Field::required($data, 'key', 'ApiScope', Value::string(...)),
            description: Field::optional($data, 'description', 'ApiScope', Value::string(...)),
            tenantRequestable: Field::optional($data, 'tenant_requestable', 'ApiScope', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'description' => $this->description,
            'tenant_requestable' => $this->tenantRequestable,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
