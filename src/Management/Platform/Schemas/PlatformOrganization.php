<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/PlatformOrganization` on the platform plane. */
readonly class PlatformOrganization implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        /** One of `customer`, `reseller`. */
        public string $type,
        /** One of `active`, `suspended`, `deleted`. */
        public string $status,
        public ?string $parentId = null,
        /** The environment it lives in. */
        public ?string $environmentId = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PlatformOrganization', Value::string(...)),
            name: Field::required($data, 'name', 'PlatformOrganization', Value::string(...)),
            slug: Field::required($data, 'slug', 'PlatformOrganization', Value::string(...)),
            type: Field::required($data, 'type', 'PlatformOrganization', Value::string(...)),
            status: Field::required($data, 'status', 'PlatformOrganization', Value::string(...)),
            parentId: Field::optional($data, 'parent_id', 'PlatformOrganization', Value::string(...)),
            environmentId: Field::optional($data, 'environment_id', 'PlatformOrganization', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'PlatformOrganization', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'status' => $this->status,
            'parent_id' => $this->parentId,
            'environment_id' => $this->environmentId,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
