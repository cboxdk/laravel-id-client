<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;
use Closure;

/**
 * One page of a cursor-paginated list. Pass `nextCursor` as `after` for the next one;
 * it is null on the last page.
 *
 * @template T
 */
readonly class Page
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public array $items = [],
        public bool $hasMore = false,
        public ?string $nextCursor = null,
    ) {}

    /**
     * @template TItem
     *
     * @param  array<string, mixed>  $body  the whole `{data, meta}` response
     * @param  Closure(array<string, mixed>): TItem  $map
     * @return self<TItem>
     */
    public static function fromResponse(array $body, Closure $map): self
    {
        $meta = Claims::object($body, 'meta');

        return new self(
            array_map($map, Claims::objects($body, 'data')),
            Claims::bool($meta, 'has_more'),
            Claims::string($meta, 'next_cursor'),
        );
    }
}
