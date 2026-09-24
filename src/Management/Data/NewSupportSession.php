<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

/**
 * `POST /v1/support-sessions`: `actorUserId` (your staff member, holding your app's
 * `support:impersonate` through an environment-wide grant) signs in to `clientId` AS
 * `userId` in `organizationId`, for `reason` (shown to the customer), for at most 60
 * minutes.
 *
 * Send `redirectUri` (registered for the app) and a PKCE S256 `codeChallenge` to receive
 * the first authorization `code` in the response.
 */
readonly class NewSupportSession
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $userId,
        public string $organizationId,
        public string $clientId,
        public string $actorUserId,
        public string $reason,
        public ?int $ttlMinutes = null,
        public array $scopes = [],
        public ?string $redirectUri = null,
        public ?string $codeChallenge = null,
        public ?string $nonce = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'client_id' => $this->clientId,
            'actor_user_id' => $this->actorUserId,
            'reason' => $this->reason,
            'ttl_minutes' => $this->ttlMinutes,
            'scopes' => $this->scopes,
            'redirect_uri' => $this->redirectUri,
            'code_challenge' => $this->codeChallenge,
            'nonce' => $this->nonce,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
