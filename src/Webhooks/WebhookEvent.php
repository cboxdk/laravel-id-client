<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

/**
 * A verified webhook event delivered by Cbox ID. `type` is the domain-event name
 * (e.g. `organization.member_added`, `role.assigned`, `directory.user.provisioned`),
 * `payload` its data, `organizationId` the tenant it happened in (when the payload
 * carries it), `deliveryId` a stable id for idempotent processing (dedupe retries),
 * and `deliveredAt` the signed timestamp. Handed to the app's registered handler.
 */
final readonly class WebhookEvent
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
    ) {}

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
