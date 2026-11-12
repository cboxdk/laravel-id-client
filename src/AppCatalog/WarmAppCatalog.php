<?php

declare(strict_types=1);

namespace Cbox\Id\Client\AppCatalog;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Reads one person's app catalog from Cbox ID and keeps it, so the app switcher opens
 * from the kept copy ({@see MachineAppCatalog}). Queued when the person signs in, and
 * when a switcher finds its kept catalog stale. Unique per person and organization while
 * queued. Tried once: if Cbox ID cannot answer, the kept catalog stays and the next
 * stale read queues another try.
 */
class WarmAppCatalog implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** The longest the uniqueness lock outlives a job that never finished. */
    public int $uniqueFor = 60;

    public function __construct(
        public readonly string $subject,
        public readonly string $organizationId,
    ) {}

    public function uniqueId(): string
    {
        return hash('sha256', json_encode([$this->subject, $this->organizationId], JSON_THROW_ON_ERROR));
    }

    public function handle(MachineAppCatalog $catalog): void
    {
        try {
            $catalog->refresh(new AppCatalogContext($this->subject, $this->organizationId));
        } catch (SignInRequired|MachineCatalogRefused|CatalogUnavailable) {
            // Recorded by refresh(): the kept catalog is dropped for someone who left,
            // and stays when Cbox ID could not answer.
        } catch (Throwable $error) {
            report($error);
        }
    }
}
