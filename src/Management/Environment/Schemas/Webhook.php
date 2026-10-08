<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Webhook` on the environment plane. */
readonly class Webhook implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $url,
        /** @var list<string> */
        public array $eventTypes,
        /** false while paused. */
        public bool $active,
        /** null when the environment owns it: it receives EVERY organization's events. */
        public ?string $organizationId = null,
        public ?int $consecutiveFailures = null,
        public ?string $lastSuccessAt = null,
        public ?string $createdAt = null,
        /**
         * The signing secret, on the create and rotate answers only — shown once, never
         * retrievable again. `null` on an idempotent replay of that answer.
         */
        public ?string $secret = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Webhook', Value::string(...)),
            url: Field::required($data, 'url', 'Webhook', Value::string(...)),
            eventTypes: Field::required($data, 'event_types', 'Webhook', Value::list(Value::string(...))),
            active: Field::required($data, 'active', 'Webhook', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'Webhook', Value::string(...)),
            consecutiveFailures: Field::optional($data, 'consecutive_failures', 'Webhook', Value::int(...)),
            lastSuccessAt: Field::optional($data, 'last_success_at', 'Webhook', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'Webhook', Value::string(...)),
            secret: Field::optional($data, 'secret', 'Webhook', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'organization_id' => $this->organizationId,
            'event_types' => $this->eventTypes,
            'active' => $this->active,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_success_at' => $this->lastSuccessAt,
            'created_at' => $this->createdAt,
            'secret' => $this->secret,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
