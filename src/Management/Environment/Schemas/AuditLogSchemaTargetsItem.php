<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AuditLogSchemaTargetsItem` on the environment plane. */
readonly class AuditLogSchemaTargetsItem implements JsonSerializable
{
    public function __construct(
        public string $type,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Field::required($data, 'type', 'AuditLogSchemaTargetsItem', Value::string(...)),
            metadata: Field::optional($data, 'metadata', 'AuditLogSchemaTargetsItem', Value::object(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
