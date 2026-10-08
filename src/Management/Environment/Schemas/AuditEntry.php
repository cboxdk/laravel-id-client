<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AuditEntry` on the environment plane. */
readonly class AuditEntry implements JsonSerializable
{
    public function __construct(
        /** A ULID; pass the last one you saw as `after`. */
        public string $id,
        /** e.g. `webhook.created`. */
        public string $action,
        /** One of `user`, `service`, `system`, `operator`, `organization_member`. */
        public string $actorType,
        /** @var array<string, mixed> */
        public array $context,
        /** For `service`, the management key's or app's id. */
        public ?string $actorId = null,
        public ?string $organizationId = null,
        public ?string $targetType = null,
        public ?string $targetId = null,
        public ?string $ip = null,
        public ?string $recordedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AuditEntry', Value::string(...)),
            action: Field::required($data, 'action', 'AuditEntry', Value::string(...)),
            actorType: Field::required($data, 'actor_type', 'AuditEntry', Value::string(...)),
            context: Field::required($data, 'context', 'AuditEntry', Value::object(...)),
            actorId: Field::optional($data, 'actor_id', 'AuditEntry', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'AuditEntry', Value::string(...)),
            targetType: Field::optional($data, 'target_type', 'AuditEntry', Value::string(...)),
            targetId: Field::optional($data, 'target_id', 'AuditEntry', Value::string(...)),
            ip: Field::optional($data, 'ip', 'AuditEntry', Value::string(...)),
            recordedAt: Field::optional($data, 'recorded_at', 'AuditEntry', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'actor_type' => $this->actorType,
            'actor_id' => $this->actorId,
            'organization_id' => $this->organizationId,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'context' => $this->context,
            'ip' => $this->ip,
            'recorded_at' => $this->recordedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
