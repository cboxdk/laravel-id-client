<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AuditLogSettings` on the environment plane. */
readonly class AuditLogSettings implements JsonSerializable
{
    public function __construct(
        /** Events are kept this many days after they arrive. */
        public int $retentionDays,
        /** Whether an action with no schema is refused. */
        public bool $strictSchemas,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            retentionDays: Field::required($data, 'retention_days', 'AuditLogSettings', Value::int(...)),
            strictSchemas: Field::required($data, 'strict_schemas', 'AuditLogSettings', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'retention_days' => $this->retentionDays,
            'strict_schemas' => $this->strictSchemas,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
