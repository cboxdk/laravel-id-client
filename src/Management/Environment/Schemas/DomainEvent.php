<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/DomainEvent` on the environment plane. */
readonly class DomainEvent implements JsonSerializable
{
    public function __construct(
        /** A ULID; pass the last one you saw as `after`. */
        public string $id,
        /** e.g. `user.created` — the webhook event name. */
        public string $type,
        /** @var array<string, mixed> */
        public array $payload,
        public ?string $organizationId = null,
        public ?string $occurredAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'DomainEvent', Value::string(...)),
            type: Field::required($data, 'type', 'DomainEvent', Value::string(...)),
            payload: Field::required($data, 'payload', 'DomainEvent', Value::object(...)),
            organizationId: Field::optional($data, 'organization_id', 'DomainEvent', Value::string(...)),
            occurredAt: Field::optional($data, 'occurred_at', 'DomainEvent', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'organization_id' => $this->organizationId,
            'occurred_at' => $this->occurredAt,
            'payload' => $this->payload,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
