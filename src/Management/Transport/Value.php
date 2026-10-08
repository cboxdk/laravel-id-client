<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use Cbox\Id\Client\Exceptions\UnexpectedResponse;
use Closure;

/**
 * Checked conversions from a decoded JSON value to the type the OpenAPI document
 * promises. Every converter takes the value and where it was found (`App.client_id`), and
 * either returns the typed value or throws {@see UnexpectedResponse} naming the spot —
 * never a silent coercion.
 *
 * The generated schema classes are built from these; so can your own code be.
 */
class Value
{
    public static function string(mixed $value, string $where): string
    {
        return is_string($value) ? $value : throw UnexpectedResponse::at($where, 'a string', $value);
    }

    public static function int(mixed $value, string $where): int
    {
        return is_int($value) ? $value : throw UnexpectedResponse::at($where, 'an integer', $value);
    }

    public static function number(mixed $value, string $where): int|float
    {
        return is_int($value) || is_float($value) ? $value : throw UnexpectedResponse::at($where, 'a number', $value);
    }

    public static function bool(mixed $value, string $where): bool
    {
        return is_bool($value) ? $value : throw UnexpectedResponse::at($where, 'a boolean', $value);
    }

    public static function mixed(mixed $value, string $where): mixed
    {
        return $value;
    }

    /** Always null: the answer to an operation that returns nothing. */
    public static function none(mixed $value, string $where): null
    {
        return null;
    }

    /**
     * A JSON object, as an array keyed by its member names.
     *
     * @return array<string, mixed>
     */
    public static function object(mixed $value, string $where): array
    {
        if (! is_array($value)) {
            throw UnexpectedResponse::at($where, 'an object', $value);
        }

        $object = [];

        foreach ($value as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    /**
     * A converter for a schema object: checks for an object, then hands it to `$from`.
     *
     * @template T
     *
     * @param  Closure(array<string, mixed>): T  $from  usually a schema's `fromArray(...)`
     * @return Closure(mixed, string): T
     */
    public static function dto(Closure $from): Closure
    {
        return static fn (mixed $value, string $where) => $from(self::object($value, $where));
    }

    /**
     * A converter for a JSON array whose every item `$item` converts.
     *
     * @template T
     *
     * @param  Closure(mixed, string): T  $item
     * @return Closure(mixed, string): list<T>
     */
    public static function list(Closure $item): Closure
    {
        return static function (mixed $value, string $where) use ($item): array {
            if (! is_array($value) || ! array_is_list($value)) {
                throw UnexpectedResponse::at($where, 'an array', $value);
            }

            $list = [];

            foreach ($value as $index => $entry) {
                $list[] = $item($entry, "{$where}[{$index}]");
            }

            return $list;
        };
    }

    /**
     * `$item`, or null for a JSON null.
     *
     * @template T
     *
     * @param  Closure(mixed, string): T  $item
     * @return Closure(mixed, string): (T|null)
     */
    public static function nullable(Closure $item): Closure
    {
        return static fn (mixed $value, string $where) => $value === null ? null : $item($value, $where);
    }
}
