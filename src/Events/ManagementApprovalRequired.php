<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Events;

use Cbox\Id\Client\Management\Transport\ApprovalContext;
use Cbox\Id\Client\Management\Transport\PendingApproval;

/**
 * A management call made through the container's clients (`CboxIdApi`) was held for a
 * person's approval. Dispatched before the client starts polling, so a listener can show
 * the binding code the person matches on their device:
 *
 *     Event::listen(ManagementApprovalRequired::class, function ($event) {
 *         Log::notice("Approve {$event->context->action}: code {$event->approval->bindingCode}");
 *     });
 */
class ManagementApprovalRequired
{
    public function __construct(
        public readonly PendingApproval $approval,
        public readonly ApprovalContext $context,
    ) {}
}
