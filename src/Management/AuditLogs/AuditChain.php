<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\AuditLogs;

use Cbox\Id\Client\Management\Environment\Schemas\AuditLogEvent;
use Cbox\Id\Client\Management\Transport\Value;

/**
 * An organization's audit events, checked on this side the way Cbox ID's own
 * `GET /audit-logs/verify` checks them: in sequence order, every sequence present, every
 * `prev_hash` naming the event before, and every `hash` equal to
 * `sha256(prev_hash ‖ canonical JSON of the event)` — recomputed here, byte for byte as the
 * server computes it.
 *
 * Tamper-evident, not tamper-proof: it shows that what you were given is one unbroken chain,
 * not that nothing was removed from its end — only the server's check sees the head.
 */
class AuditChain
{
    /** The `prev_hash` of an organization's first event: 64 zeros. */
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Check events, in any order (they are sorted by `sequence`).
     *
     * @param  iterable<AuditLogEvent|array<string, mixed>>  $events  as the API returns them
     * @param  string|null  $previousHash  the hash the first event must chain from. Default: 64 zeros when the
     *                                     first event is sequence 1; otherwise the first event's own `prev_hash`,
     *                                     taken on trust — pass the hash you kept for the event before it to check
     *                                     that link too
     */
    public static function verify(iterable $events, ?string $previousHash = null): ChainVerification
    {
        $all = [];

        foreach ($events as $event) {
            $all[] = $event instanceof AuditLogEvent ? $event->toArray() : $event;
        }

        $sequenceOf = static fn (array $event): int => Value::int($event['sequence'] ?? null, 'event.sequence');
        usort($all, static fn (array $a, array $b): int => $sequenceOf($a) <=> $sequenceOf($b));

        $first = $all[0] ?? null;
        $firstSequence = $first === null ? 1 : $sequenceOf($first);
        $previous = $previousHash ?? ($first === null || $firstSequence === 1 ? self::GENESIS : self::string($first, 'prev_hash'));
        $expected = $firstSequence;
        $verified = 0;

        foreach ($all as $event) {
            if ($sequenceOf($event) !== $expected) {
                return self::outcome('missing', $verified, $firstSequence, $expected);
            }

            if (! hash_equals($previous, self::string($event, 'prev_hash'))) {
                return self::outcome('link', $verified, $firstSequence, $expected);
            }

            $hash = self::string($event, 'hash');

            if (! hash_equals(self::hash($previous, $event), $hash)) {
                return self::outcome('hash', $verified, $firstSequence, $expected);
            }

            $previous = $hash;
            $expected++;
            $verified++;
        }

        return self::outcome(null, $verified, $firstSequence, $expected);
    }

    /**
     * `sha256(previousHash ‖ canonicalJson(document))`, lowercase hex.
     *
     * @param  AuditLogEvent|array<string, mixed>  $event
     */
    public static function hash(string $previousHash, AuditLogEvent|array $event): string
    {
        return hash('sha256', $previousHash.CanonicalJson::encode(self::document($event)));
    }

    /**
     * The part of an event its hash covers, in the server's shape: what the sender said,
     * plus where in the chain it sits. Metadata is an object or null — never `[]`.
     *
     * @param  AuditLogEvent|array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public static function document(AuditLogEvent|array $event): array
    {
        $event = $event instanceof AuditLogEvent ? $event->toArray() : $event;
        $actor = is_array($event['actor'] ?? null) ? $event['actor'] : [];
        $context = is_array($event['context'] ?? null) ? $event['context'] : [];
        $targets = is_array($event['targets'] ?? null) ? $event['targets'] : [];

        return [
            'id' => $event['id'] ?? null,
            'organization_id' => $event['organization_id'] ?? null,
            'sequence' => $event['sequence'] ?? null,
            'action' => $event['action'] ?? null,
            'occurred_at' => $event['occurred_at'] ?? null,
            'actor' => self::party($actor),
            'targets' => array_values(array_map(static fn (mixed $target): array => self::party(is_array($target) ? $target : []), $targets)),
            'context' => [
                'location' => $context['location'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
            ],
            'metadata' => self::map($event['metadata'] ?? null),
        ];
    }

    /** @param 'missing'|'link'|'hash'|null $reason */
    private static function outcome(?string $reason, int $verified, int $firstSequence, int $expected): ChainVerification
    {
        return new ChainVerification(
            $reason === null,
            $verified,
            $verified === 0 ? null : $firstSequence,
            $verified === 0 ? null : $expected - 1,
            $reason === null ? null : $expected,
            $reason,
        );
    }

    /**
     * @param  array<array-key, mixed>  $party
     * @return array<string, mixed>
     */
    private static function party(array $party): array
    {
        return [
            'id' => $party['id'] ?? null,
            'type' => $party['type'] ?? null,
            'name' => $party['name'] ?? null,
            'metadata' => self::map($party['metadata'] ?? null),
        ];
    }

    /** @return array<array-key, mixed>|null */
    private static function map(mixed $metadata): ?array
    {
        return is_array($metadata) && $metadata !== [] ? $metadata : null;
    }

    /** @param array<array-key, mixed> $event */
    private static function string(array $event, string $key): string
    {
        return is_string($event[$key] ?? null) ? $event[$key] : '';
    }
}
