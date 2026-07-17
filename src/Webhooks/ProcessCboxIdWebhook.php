<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Webhooks;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs the app's webhook handlers off the request thread. The receiver verifies the
 * signature and enqueues this — so the HTTP response is fast (no slow handlers, no
 * dispatcher timeout/retry) and the real work (seat allocation, API calls) happens on
 * a worker. The event is plain scalars/array, so it serialises cleanly onto any queue.
 */
final class ProcessCboxIdWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public readonly WebhookEvent $event) {}

    public function handle(WebhookHandlers $handlers): void
    {
        $handlers->dispatch($this->event);
    }
}
