<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * The person has not connected this provider (404 `not_connected`). Send them to `$connectUrl`.
 */
class PipeNotConnected extends PipeLeaseFailed {}
