<?php

declare(strict_types=1);

namespace Cbox\Id\Client\UserProperties;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Reads a person's kept properties from Cbox ID again, so the request that found them
 * stale did not have to wait ({@see KeptUserProperties}). Unique per person and
 * namespace while queued. Tried once: if Cbox ID is down the error is reported, the kept
 * copy stays, and the next stale read queues another try.
 */
class RefreshKeptUserProperties implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** The longest the uniqueness lock outlives a job that never finished. */
    public int $uniqueFor = 60;

    public function __construct(
        public readonly string $user,
        public readonly string $namespace,
    ) {}

    public function uniqueId(): string
    {
        return hash('sha256', json_encode([$this->user, $this->namespace], JSON_THROW_ON_ERROR));
    }

    public function handle(KeptUserProperties $properties): void
    {
        try {
            $properties->refresh($this->user, $this->namespace);
        } catch (Throwable $error) {
            // Cbox ID away: the kept copy stays. Access taken away: refresh() dropped it.
            report($error);
        }
    }
}
