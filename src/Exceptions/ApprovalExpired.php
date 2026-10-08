<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\Management\Transport\PendingApproval;

/** Nobody approved the action before the approval expired. Ask again with a new request. */
class ApprovalExpired extends ApprovalException
{
    public static function of(PendingApproval $approval): self
    {
        return new self("Approval {$approval->id} expired before it was approved.", 'expired', $approval);
    }
}
