<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AuditLogSchema` on the environment plane. */
readonly class AuditLogSchema implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $action,
        /** Starts at 1; every replacement is the next. */
        public int $version,
        /**
         * The target types this action's events may name; null allows any.
         *
         * @var list<AuditLogSchemaTargetsItem>|null
         */
        public ?array $targets = null,
        /**
         * A metadata schema (a subset of JSON Schema).
         *
         * @var array<string, mixed>|null
         */
        public ?array $actorMetadata = null,
        /**
         * A metadata schema (a subset of JSON Schema).
         *
         * @var array<string, mixed>|null
         */
        public ?array $metadata = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AuditLogSchema', Value::string(...)),
            action: Field::required($data, 'action', 'AuditLogSchema', Value::string(...)),
            version: Field::required($data, 'version', 'AuditLogSchema', Value::int(...)),
            targets: Field::optional($data, 'targets', 'AuditLogSchema', Value::list(Value::dto(AuditLogSchemaTargetsItem::fromArray(...)))),
            actorMetadata: Field::optional($data, 'actor_metadata', 'AuditLogSchema', Value::object(...)),
            metadata: Field::optional($data, 'metadata', 'AuditLogSchema', Value::object(...)),
            createdAt: Field::optional($data, 'created_at', 'AuditLogSchema', Value::string(...)),
            updatedAt: Field::optional($data, 'updated_at', 'AuditLogSchema', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'version' => $this->version,
            'targets' => $this->targets === null ? null : array_map(static fn (AuditLogSchemaTargetsItem $item) => $item->toArray(), $this->targets),
            'actor_metadata' => $this->actorMetadata,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
