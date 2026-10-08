<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\Management\Transport\PendingApproval;

/** The person declined the action on their device. Do not retry it unprompted. */
class ApprovalDenied extends ApprovalException
{
    public static function of(PendingApproval $approval): self
    {
        return new self("Approval {$approval->id} was denied.", 'denied', $approval);
    }
}
