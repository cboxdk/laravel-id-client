<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A publishable key. Returned in full: it is public by design; the origins are the control.
 *
 * `#/components/schemas/FrontendKey` on the environment plane.
 */
readonly class FrontendKey implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $key,
        /** One of `test`, `live`. */
        public string $mode,
        /** @var list<string> */
        public array $origins,
        public bool $active,
        public ?string $createdAt = null,
        public ?string $lastUsedAt = null,
        public ?string $revokedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'FrontendKey', Value::string(...)),
            name: Field::required($data, 'name', 'FrontendKey', Value::string(...)),
            key: Field::required($data, 'key', 'FrontendKey', Value::string(...)),
            mode: Field::required($data, 'mode', 'FrontendKey', Value::string(...)),
            origins: Field::required($data, 'origins', 'FrontendKey', Value::list(Value::string(...))),
            active: Field::required($data, 'active', 'FrontendKey', Value::bool(...)),
            createdAt: Field::optional($data, 'created_at', 'FrontendKey', Value::string(...)),
            lastUsedAt: Field::optional($data, 'last_used_at', 'FrontendKey', Value::string(...)),
            revokedAt: Field::optional($data, 'revoked_at', 'FrontendKey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'mode' => $this->mode,
            'origins' => $this->origins,
            'active' => $this->active,
            'created_at' => $this->createdAt,
            'last_used_at' => $this->lastUsedAt,
            'revoked_at' => $this->revokedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
