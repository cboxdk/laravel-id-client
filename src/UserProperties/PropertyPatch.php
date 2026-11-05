<?php

declare(strict_types=1);

namespace Cbox\Id\Client\UserProperties;

use InvalidArgumentException;
use stdClass;

/** JSON at the API boundary: objects, arrays and null retain their distinct meanings. */
readonly class PropertyPatch
{
    /** @param list<string> $remove */
    public function __construct(public int $version, public stdClass $set = new stdClass, public array $remove = [])
    {
        if ($version < 0) {
            throw new InvalidArgumentException('A property patch needs a nonnegative version and a list of removed keys.');
        }
    }
}
