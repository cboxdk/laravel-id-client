<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Shell;

/** One page of the person's organizations, and the cursor to the next. */
readonly class MirroredOrganizationPage
{
    /**
     * @param  list<MirroredOrganization>  $items
     */
    public function __construct(
        public array $items = [],
        public ?string $nextCursor = null,
    ) {}
}
