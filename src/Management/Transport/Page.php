<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * One page of a paged list. Iterate it for its items; ask for the next page with
 * `nextCursor` (as `after`, on the environment plane) or `nextPage` (as `page`, on the
 * workspace plane) — or skip all of that with the method's `…All()` twin, which walks
 * every page lazily.
 *
 * @template-covariant T
 *
 * @implements IteratorAggregate<int, T>
 */
readonly class Page implements Countable, IteratorAggregate
{
    /**
     * @param  list<T>  $items
     * @param  ApiResponse<list<T>>  $response  the response this page came in, for its request id and headers
     */
    public function __construct(
        public array $items,
        public bool $hasMore,
        public ?string $nextCursor,
        public ?int $nextPage,
        public ?int $total,
        public ApiResponse $response,
    ) {}

    /** @return Traversable<int, T> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
