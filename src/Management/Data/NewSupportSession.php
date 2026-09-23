<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/support-sessions`. `reason` is required and lands in the audit log on both
 * sides; `ttlMinutes` is capped at 60 by Cbox ID whatever you ask for. `actorUserId` is
 * the member of staff (who must hold `support:impersonate` for the app).
 */
readonly class NewSupportSession
{
    public function __construct(
        public string $userId,
        public string $organizationId,
        public string $clientId,
        public string $reason,
        public ?string $actorUserId = null,
        public ?int $ttlMinutes = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'client_id' => $this->clientId,
            'reason' => $this->reason,
            'actor_user_id' => $this->actorUserId,
            'ttl_minutes' => $this->ttlMinutes,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
