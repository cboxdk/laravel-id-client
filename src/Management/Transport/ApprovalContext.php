<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/** Handed to `onApprovalRequired` alongside the approval: what was held. */
readonly class ApprovalContext
{
    public function __construct(
        /** The action held, e.g. `apps.secrets.rotate`; null for a route that is not an action. */
        public ?string $action,
        public ?Danger $danger,
        public string $method,
        public string $path,
    ) {}
}
