<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Organization` on the environment plane. */
readonly class Organization implements JsonSerializable
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
        public ?string $createdAt = null,
        /**
         * Free-form text values kept on the organization; null when there are none.
         *
         * @var array<string, mixed>|null
         */
        public ?array $metadata = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Organization', Value::string(...)),
            name: Field::required($data, 'name', 'Organization', Value::string(...)),
            slug: Field::required($data, 'slug', 'Organization', Value::string(...)),
            type: Field::required($data, 'type', 'Organization', Value::string(...)),
            status: Field::required($data, 'status', 'Organization', Value::string(...)),
            parentId: Field::optional($data, 'parent_id', 'Organization', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'Organization', Value::string(...)),
            metadata: Field::optional($data, 'metadata', 'Organization', Value::object(...)),
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
            'created_at' => $this->createdAt,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
