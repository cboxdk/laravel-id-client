<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/** How a list pages: an opaque `after` cursor, or a `page` number. */
enum Pagination: string
{
    case Cursor = 'cursor';
    case Page = 'page';

    /** The query parameter that asks for the next page. */
    public function parameter(): string
    {
        return $this === self::Cursor ? 'after' : 'page';
    }
}
