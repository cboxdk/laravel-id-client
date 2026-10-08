<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\Management\Transport\PendingApproval;

/**
 * An action held for a person's approval did not get it. `$reason` says how: `denied`
 * (they said no), `expired` (nobody answered in time), or `consumed` (the approval was
 * already spent by another request, or the request kept being held).
 */
class ApprovalException extends CboxIdException
{
    /**
     * @param  'denied'|'expired'|'consumed'  $reason
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly PendingApproval $approval,
    ) {
        parent::__construct($message);
    }
}
