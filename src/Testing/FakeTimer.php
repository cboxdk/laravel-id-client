<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Testing;

use Cbox\Id\Client\Grants\Timer;

/**
 * A clock that only moves when something sleeps on it.
 *
 *     $timer = new FakeTimer;
 *     $this->app->instance(Timer::class, $timer);
 *
 *     app(BackchannelAuthentication::class)->waitForToken($pending);
 *
 *     expect($timer->sleeps)->toBe([5, 5, 10]);
 */
class FakeTimer implements Timer
{
    /** @var list<int> Every sleep, in order. */
    public array $sleeps = [];

    public function __construct(public int $time = 1_700_000_000) {}

    public function now(): int
    {
        return $this->time;
    }

    public function sleep(int $seconds): void
    {
        $this->sleeps[] = $seconds;
        $this->time += max(0, $seconds);
    }
}
