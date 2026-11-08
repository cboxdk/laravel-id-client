<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Grants;

/** The wall clock and a real `sleep()`. */
class SystemTimer implements Timer
{
    public function now(): int
    {
        return time();
    }

    public function sleep(int $seconds): void
    {
        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
