<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/PlatformEnvironment` on the platform plane. */
readonly class PlatformEnvironment implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $status,
        /** The verified domain it serves on — null until one is verified by DNS. */
        public ?string $domain = null,
        /** The project that owns it; null for one no customer owns yet. */
        public ?string $projectId = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PlatformEnvironment', Value::string(...)),
            name: Field::required($data, 'name', 'PlatformEnvironment', Value::string(...)),
            slug: Field::required($data, 'slug', 'PlatformEnvironment', Value::string(...)),
            status: Field::required($data, 'status', 'PlatformEnvironment', Value::string(...)),
            domain: Field::optional($data, 'domain', 'PlatformEnvironment', Value::string(...)),
            projectId: Field::optional($data, 'project_id', 'PlatformEnvironment', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'PlatformEnvironment', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'status' => $this->status,
            'project_id' => $this->projectId,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
