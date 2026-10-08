<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * One audit event your app sent. `hash` covers `id`, `organization_id`, `sequence`,
 * `action`, `occurred_at`, `actor`, `targets`, `context` and `metadata`, chained to
 * the organization's previous event by `prev_hash` — see the Audit Logs guide for how
 * to verify it yourself.
 *
 * `#/components/schemas/AuditLogEvent` on the environment plane.
 */
readonly class AuditLogEvent implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        /** The event's place in its organization's chain, from 1. */
        public int $sequence,
        /** e.g. `invoice.voided`. */
        public string $action,
        /** When it happened, as the sender said, to the millisecond. */
        public string $occurredAt,
        public AuditLogEventActor $actor,
        /** @var list<AuditLogEventTargetsItem> */
        public array $targets,
        public AuditLogEventContext $context,
        /** The previous event's hash; 64 zeros for an organization's first. */
        public string $prevHash,
        public string $hash,
        /** @var array<string, mixed>|null */
        public ?array $metadata = null,
        /** The version of its action's schema it was checked against; null when the action has none. */
        public ?int $schemaVersion = null,
        /** When it arrived. Retention counts from here. */
        public ?string $receivedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AuditLogEvent', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'AuditLogEvent', Value::string(...)),
            sequence: Field::required($data, 'sequence', 'AuditLogEvent', Value::int(...)),
            action: Field::required($data, 'action', 'AuditLogEvent', Value::string(...)),
            occurredAt: Field::required($data, 'occurred_at', 'AuditLogEvent', Value::string(...)),
            actor: Field::required($data, 'actor', 'AuditLogEvent', Value::dto(AuditLogEventActor::fromArray(...))),
            targets: Field::required($data, 'targets', 'AuditLogEvent', Value::list(Value::dto(AuditLogEventTargetsItem::fromArray(...)))),
            context: Field::required($data, 'context', 'AuditLogEvent', Value::dto(AuditLogEventContext::fromArray(...))),
            prevHash: Field::required($data, 'prev_hash', 'AuditLogEvent', Value::string(...)),
            hash: Field::required($data, 'hash', 'AuditLogEvent', Value::string(...)),
            metadata: Field::optional($data, 'metadata', 'AuditLogEvent', Value::object(...)),
            schemaVersion: Field::optional($data, 'schema_version', 'AuditLogEvent', Value::int(...)),
            receivedAt: Field::optional($data, 'received_at', 'AuditLogEvent', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'sequence' => $this->sequence,
            'action' => $this->action,
            'occurred_at' => $this->occurredAt,
            'actor' => $this->actor->toArray(),
            'targets' => array_map(static fn (AuditLogEventTargetsItem $item) => $item->toArray(), $this->targets),
            'context' => $this->context->toArray(),
            'metadata' => $this->metadata,
            'schema_version' => $this->schemaVersion,
            'received_at' => $this->receivedAt,
            'prev_hash' => $this->prevHash,
            'hash' => $this->hash,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
