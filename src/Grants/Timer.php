<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

use Cbox\Id\Client\Testing\FakeTimer;

/**
 * The clock and the sleep a polling grant waits on.
 *
 * A seam for tests: {@see FakeTimer} advances its clock on
 * `sleep()` instead of blocking, so a five-minute device flow runs in a millisecond.
 */
interface Timer
{
    /** The current Unix time, in seconds. */
    public function now(): int;

    /** Block for `$seconds`. */
    public function sleep(int $seconds): void;
}
