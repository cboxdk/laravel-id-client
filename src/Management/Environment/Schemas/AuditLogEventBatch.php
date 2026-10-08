<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AuditLogEventBatch` on the environment plane. */
readonly class AuditLogEventBatch implements JsonSerializable
{
    public function __construct(
        /**
         * The recorded events, in the order they were sent.
         *
         * @var list<AuditLogEvent>
         */
        public array $events,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            events: Field::required($data, 'events', 'AuditLogEventBatch', Value::list(Value::dto(AuditLogEvent::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'events' => array_map(static fn (AuditLogEvent $item) => $item->toArray(), $this->events),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
