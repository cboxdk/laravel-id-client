<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Decisions;

use Cbox\Id\Client\Contracts\KeptPermissionDecisions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Asks Cbox ID again about a kept decision that has gone stale, so the page that noticed
 * did not have to wait ({@see KeptDecisions}). Unique per question while queued: a burst
 * of page views queues one request. Tried once; if Cbox ID is down the error is reported,
 * the kept answer stays, and the next stale page view queues another try.
 */
class RefreshKeptDecision implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** The longest the uniqueness lock outlives a job that never finished. */
    public int $uniqueFor = 60;

    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $subject,
        public readonly ?string $organization,
        public readonly array $permissions,
    ) {}

    public function uniqueId(): string
    {
        $permissions = $this->permissions;
        sort($permissions);

        return hash('sha256', json_encode([$this->subject, $this->organization, $permissions], JSON_THROW_ON_ERROR));
    }

    public function handle(KeptPermissionDecisions $decisions): void
    {
        try {
            $decisions->refresh($this->subject, $this->organization, $this->permissions);
        } catch (Throwable $error) {
            // Cbox ID away: the kept answer stays and the next stale page view asks again.
            report($error);
        }
    }
}
