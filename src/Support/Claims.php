<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Support;

use DateTimeImmutable;
use Throwable;

/**
 * Typed reads out of a decoded JSON object — a claim set, or an API response body.
 *
 * Everything that arrives over the wire is `mixed` until something checks it, and the
 * checks are the same few everywhere: a non-empty string, a list of strings, an int.
 * Done once here so a value object's `fromArray()` reads as the shape it expects rather
 * than as a wall of `is_string` guards — and so a claim that arrives with the wrong type
 * is ABSENT, never coerced into something that grants.
 *
 * @internal
 */
final class Claims
{
    /** @param array<array-key, mixed> $data */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<array-key, mixed> $data */
    public static function requiredString(array $data, string $key): string
    {
        return self::string($data, $key) ?? '';
    }

    /** @param array<array-key, mixed> $data */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : null);
    }

    /** @param array<array-key, mixed> $data */
    public static function bool(array $data, string $key, bool $default = false): bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : $default;
    }

    /**
     * A list of non-empty strings. A space-separated string is split, because OAuth puts
     * scopes in one and a claim that is sometimes a string is still the same claim.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    public static function strings(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (is_string($value)) {
            $value = explode(' ', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '' && ! in_array($item, $out, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * A nested JSON object, string-keyed.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    public static function object(array $data, string $key): array
    {
        return self::normalize($data[$key] ?? null);
    }

    /**
     * A list of nested JSON objects, each string-keyed. Anything that is not an object is
     * dropped rather than failing the whole list.
     *
     * @param  array<array-key, mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function objects(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $out[] = self::normalize($item);
            }
        }

        return $out;
    }

    /**
     * A unix timestamp or an ISO-8601 string; anything else is null ("not known").
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function time(array $data, string $key): ?DateTimeImmutable
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return (new DateTimeImmutable)->setTimestamp($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $out[$key] = $item;
            }
        }

        return $out;
    }
}
