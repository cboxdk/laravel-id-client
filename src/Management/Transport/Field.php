<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Cbox\Id\Client\Exceptions\UnexpectedResponse;
use Closure;

/**
 * Reads one member of a decoded JSON object through a {@see Value} converter: `required()`
 * for a member the schema promises is present and not null, `optional()` for everything
 * else (absent and null both read as null).
 */
class Field
{
    /**
     * @template T
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(mixed, string): T  $convert
     * @return T
     */
    public static function required(array $data, string $key, string $where, Closure $convert): mixed
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            throw UnexpectedResponse::at("{$where}.{$key}", 'a value', null);
        }

        return $convert($value, "{$where}.{$key}");
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $data
     * @param  Closure(mixed, string): T  $convert
     * @return T|null
     */
    public static function optional(array $data, string $key, string $where, Closure $convert): mixed
    {
        $value = $data[$key] ?? null;

        return $value === null ? null : $convert($value, "{$where}.{$key}");
    }
}
