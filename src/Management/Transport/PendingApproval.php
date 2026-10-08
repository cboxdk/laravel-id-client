<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/** An approval a credential's policy asked for — what a `202 approval_required` carries. */
readonly class PendingApproval
{
    public function __construct(
        public string $id,
        public string $status = 'pending',
        /** Show this to the person: the same code appears on the device they approve on. */
        public string $bindingCode = '',
        public string $expiresAt = '',
        public string $pollUrl = '',
    ) {}
}
