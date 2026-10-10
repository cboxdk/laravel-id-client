<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The provider could not refresh the token just now (503). Retry after `$retryAfter` seconds.
 */
class PipeTemporarilyUnavailable extends PipeLeaseFailed {}
