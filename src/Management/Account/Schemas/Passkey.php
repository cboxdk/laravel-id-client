<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Passkey` on the account plane. */
readonly class Passkey implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** What you call it — one to 64 characters. */
        public string $name,
        public ?string $createdAt = null,
        /** When it last signed you in; null if it never has since this was recorded. */
        public ?string $lastUsedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Passkey', Value::string(...)),
            name: Field::required($data, 'name', 'Passkey', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'Passkey', Value::string(...)),
            lastUsedAt: Field::optional($data, 'last_used_at', 'Passkey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->createdAt,
            'last_used_at' => $this->lastUsedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
