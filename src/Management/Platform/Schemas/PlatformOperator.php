<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/PlatformOperator` on the platform plane. */
readonly class PlatformOperator implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
        /** One of `active`, `suspended`. */
        public string $status,
        public ?string $name = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PlatformOperator', Value::string(...)),
            email: Field::required($data, 'email', 'PlatformOperator', Value::string(...)),
            status: Field::required($data, 'status', 'PlatformOperator', Value::string(...)),
            name: Field::optional($data, 'name', 'PlatformOperator', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'PlatformOperator', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
