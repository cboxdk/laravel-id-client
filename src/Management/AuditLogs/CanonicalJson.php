<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\AuditLogs;

/**
 * Canonical JSON exactly as Cbox ID hashes an audit event:
 *
 * - objects (non-list arrays) sorted by key, compared as byte strings, at every depth;
 * - lists kept in order;
 * - slashes and non-ASCII characters written as-is, not escaped.
 *
 * The same rules, and the same `json_encode` flags, as the server's own encoder — so the
 * bytes match, PHP's quirks included (an empty object is `[]`, U+2028 is escaped). Floats
 * render in PHP's shortest round-trip form; keep them out of anything you hash if you can.
 */
class CanonicalJson
{
    public const FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * @param  array<array-key, mixed>  $value
     */
    public static function encode(array $value): string
    {
        return json_encode(self::normalize($value), self::FLAGS);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    public static function normalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::normalize($item);
            }
        }

        return $value;
    }
}
