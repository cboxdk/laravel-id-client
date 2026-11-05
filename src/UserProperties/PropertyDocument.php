<?php

declare(strict_types=1);

namespace Cbox\Id\Client\UserProperties;

use stdClass;

/** Versioned JSON document returned by the property API, without lossy array conversion. */
readonly class PropertyDocument
{
    public function __construct(public string $userId, public string $namespace, public stdClass $data = new stdClass, public int $version = 0) {}
}
