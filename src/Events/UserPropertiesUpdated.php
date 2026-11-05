<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Events;

use Cbox\Id\Client\Webhooks\EventType;
use Cbox\Id\Client\Webhooks\WebhookEvent;
use InvalidArgumentException;

/**
 * Verified invalidation, dispatched on the webhook worker. No property values.
 * Delivery is at least once: consumers deduplicate and compare document versions.
 * This event deliberately does not implement ShouldBroadcast.
 */
final readonly class UserPropertiesUpdated
{
    /** @param list<string> $changedKeys */
    private function __construct(
        public string $issuer,
        public string $eventId,
        public string $userId,
        public string $namespace,
        public array $changedKeys,
        public int $version,
        public ?string $clientId,
        public string $occurredAt,
        public WebhookEvent $webhook,
    ) {}

    public static function supports(string $type): bool
    {
        return in_array($type, [EventType::UserPreferencesUpdated->value, EventType::UserMetadataUpdated->value], true);
    }

    /** @throws InvalidArgumentException when the signed event violates the contract. */
    public static function fromWebhook(WebhookEvent $event, string $issuer): self
    {
        $data = $event->payload;
        foreach (['event_id', 'user_id', 'namespace', 'occurred_at'] as $key) {
            if (! is_string($data[$key] ?? null) || $data[$key] === '') {
                throw new InvalidArgumentException('Invalid user properties event field: '.$key);
            }
        }

        $keys = $data['changed_keys'] ?? null;
        if (! self::supports($event->type)
            || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $data['namespace'])
            || ($event->is(EventType::UserPreferencesUpdated) !== ($data['namespace'] === 'preferences'))
            || ! is_int($data['version'] ?? null) || $data['version'] < 1
            || ! is_array($keys) || ! array_is_list($keys) || $keys === []
            || ! array_key_exists('client_id', $data)
            || ($data['client_id'] !== null && (! is_string($data['client_id']) || $data['client_id'] === ''))
        ) {
            throw new InvalidArgumentException('Invalid user properties event.');
        }
        foreach ($keys as $key) {
            if (! is_string($key) || ! preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,127}$/D', $key)) {
                throw new InvalidArgumentException('Invalid changed property key.');
            }
        }

        return new self(rtrim($issuer, '/'), $data['event_id'], $data['user_id'], $data['namespace'],
            $keys, $data['version'], $data['client_id'], $data['occurred_at'], $event);
    }
}
