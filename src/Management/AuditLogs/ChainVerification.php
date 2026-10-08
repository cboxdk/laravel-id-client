<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\AuditLogs;

/**
 * What {@see AuditChain::verify()} found — the server's `AuditLogVerification`, minus its
 * view of the chain's head (a client cannot see whether events were removed from the end).
 */
readonly class ChainVerification
{
    /**
     * @param  'missing'|'link'|'hash'|null  $reason  `missing` (a sequence gap), `link` (`prev_hash` does not name
     *                                                the event before), `hash` (the event changed)
     */
    public function __construct(
        public bool $valid = true,
        public int $verifiedCount = 0,
        public ?int $firstSequence = null,
        public ?int $lastSequence = null,
        public ?int $brokenAtSequence = null,
        public ?string $reason = null,
    ) {}
}
