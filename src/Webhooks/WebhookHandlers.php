<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

/**
 * The registry an app registers provisioning hooks against. Register in a service
 * provider's boot():
 *
 *     CboxIdWebhooks::on('organization.member_added', fn (WebhookEvent $e) => …);
 *     CboxIdWebhooks::on('role.assigned',            fn (WebhookEvent $e) => …);
 *     CboxIdWebhooks::on('*',                        fn (WebhookEvent $e) => …); // all
 *
 * The SDK's verified webhook controller calls every handler registered for the
 * event's type, plus any wildcard handlers. Cbox ID owns identity + assignment;
 * these hooks are how your app reacts out-of-band (seat allocation, deprovisioning,
 * pre-provisioning) without a token round-trip.
 *
 * An event's old name and its `id.*` name are one event ({@see EventNames}): a handler
 * registered for `organization.member_added` also runs for `id.organization.member_added`,
 * and the other way round.
 */
class WebhookHandlers
{
    /** @var array<string, list<callable(WebhookEvent): void>> */
    private array $handlers = [];

    /**
     * @param  callable(WebhookEvent): void  $handler
     */
    public function on(EventType|string $eventType, callable $handler): void
    {
        $this->handlers[$eventType instanceof EventType ? $eventType->value : $eventType][] = $handler;
    }

    /**
     * Whether any handler would run for this event type (type-specific or wildcard).
     * Lets the receiver skip enqueuing a job nothing will process.
     */
    public function hasHandlerFor(string $eventType): bool
    {
        return $this->handlersFor($eventType) !== [];
    }

    /**
     * Invoke every handler registered for this event's type and every wildcard
     * handler. Returns how many ran — 0 means the app subscribes to nothing for it.
     */
    public function dispatch(WebhookEvent $event): int
    {
        $handlers = $this->handlersFor($event->type);

        foreach ($handlers as $handler) {
            $handler($event);
        }

        return count($handlers);
    }

    /**
     * Every handler for an event type, under either of its names, then the wildcard ones.
     *
     * @return list<callable(WebhookEvent): void>
     */
    private function handlersFor(string $eventType): array
    {
        $handlers = [];

        foreach (EventNames::aliases($eventType) as $name) {
            if ($name === EventType::WILDCARD) {
                continue;
            }

            foreach ($this->handlers[$name] ?? [] as $handler) {
                // One handler registered under both names runs once.
                if (! in_array($handler, $handlers, true)) {
                    $handlers[] = $handler;
                }
            }
        }

        return [...$handlers, ...($this->handlers[EventType::WILDCARD] ?? [])];
    }

    /**
     * The event types with at least one registered handler (excluding wildcard) —
     * used to advise which subscriptions to register on the Cbox ID endpoint.
     *
     * @return list<string>
     */
    public function subscribedTypes(): array
    {
        return array_values(array_filter(array_keys($this->handlers), static fn (string $type): bool => $type !== '*'));
    }
}
