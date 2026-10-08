<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AuditLogEventActor` on the environment plane. */
readonly class AuditLogEventActor implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $type,
        public ?string $name = null,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AuditLogEventActor', Value::string(...)),
            type: Field::required($data, 'type', 'AuditLogEventActor', Value::string(...)),
            name: Field::optional($data, 'name', 'AuditLogEventActor', Value::string(...)),
            metadata: Field::optional($data, 'metadata', 'AuditLogEventActor', Value::object(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
