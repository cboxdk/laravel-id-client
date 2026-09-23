<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

/**
 * A verified webhook event delivered by Cbox ID. `type` is the domain-event name
 * (e.g. `organization.member_added`, `role.assigned`, `directory.user.provisioned`),
 * `payload` its data, `organizationId` the tenant it happened in (when the payload
 * carries it), `deliveryId` a stable id for idempotent processing (dedupe retries),
 * and `deliveredAt` the signed timestamp. Handed to the app's registered handler.
 *
 * `sequence` is the endpoint's delivery counter: it goes up by exactly one per event
 * sent to your endpoint, so a jump between two you processed means one is still in
 * flight or was lost — and two events can be put back in the order they happened even
 * when retries deliver them out of it. Null from an instance that does not send it.
 */
readonly class WebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public array $payload = [],
        public ?string $organizationId = null,
        public ?string $deliveryId = null,
        public int $deliveredAt = 0,
        public ?int $sequence = null,
    ) {}

    public function is(EventType|string $type): bool
    {
        return $this->type === ($type instanceof EventType ? $type->value : $type);
    }

    /** The event as a catalogued {@see EventType}, or null for one this SDK does not list. */
    public function eventType(): ?EventType
    {
        return EventType::tryFrom($this->type);
    }

    /**
     * A string field from the payload, or null when absent/non-string — the ergonomic
     * way to read the common ids (`user_id`, `role_id`, …) without manual guards.
     */
    public function string(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
