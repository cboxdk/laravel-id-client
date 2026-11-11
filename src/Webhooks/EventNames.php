<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

/**
 * Cbox ID's webhook event names, old and new.
 *
 * Cbox ID is adding its product key to every event type: `organization.member_added`
 * becomes `id.organization.member_added`, and `user.login` becomes `id.user.signed_in`
 * (the standard wants a past verb). For one release a delivery may carry either name, so
 * the SDK treats the two as one event: a handler registered for either runs for both, and
 * {@see WebhookEvent::is()} answers the same for both.
 */
final class EventNames
{
    /** The product key Cbox ID puts in front of its event types. */
    public const string PREFIX = 'id.';

    /**
     * Old names whose new name is not just the prefix added.
     *
     * @var array<string, string>
     */
    public const array RENAMED = [
        'user.login' => 'id.user.signed_in',
    ];

    /** The `id.*` name of an event type, whichever name it was given in. */
    public static function current(string $type): string
    {
        if ($type === EventType::WILDCARD || str_starts_with($type, self::PREFIX)) {
            return $type;
        }

        return self::RENAMED[$type] ?? self::PREFIX.$type;
    }

    /** The name an event type had before the `id.*` names, whichever name it was given in. */
    public static function legacy(string $type): string
    {
        if (! str_starts_with($type, self::PREFIX)) {
            return $type;
        }

        $old = array_search($type, self::RENAMED, true);

        return is_string($old) ? $old : substr($type, strlen(self::PREFIX));
    }

    /**
     * Both names of an event type: the one given first.
     *
     * @return list<string>
     */
    public static function aliases(string $type): array
    {
        return array_values(array_unique([$type, self::legacy($type), self::current($type)]));
    }

    /** Whether two event type names are the same event. */
    public static function same(string $a, string $b): bool
    {
        return $a === $b || self::legacy($a) === self::legacy($b);
    }
}
