<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

/**
 * A support session: a member of staff (`actorId`) signed in to one app AS a customer's
 * user, for a stated reason, for at most an hour. Every token carries
 * `act: {"sub": actorId}` and there is never a refresh token.
 *
 * `code` is the first authorization code — present when the request sent `redirectUri`
 * and a PKCE `codeChallenge`, and shown once. Redeem it at the token endpoint with the
 * verifier and `redirectUri`.
 */
readonly class SupportSession
{
    /**
     * @param  list<string>  $scopes
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $userId,
        public string $organizationId,
        public string $clientId,
        public string $actorId,
        public string $reason,
        public array $scopes = [],
        public ?DateTimeImmutable $expiresAt = null,
        #[\SensitiveParameter]
        public ?string $code = null,
        public ?string $redirectUri = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            userId: Claims::requiredString($data, 'user_id'),
            organizationId: Claims::requiredString($data, 'organization_id'),
            clientId: Claims::requiredString($data, 'client_id'),
            actorId: Claims::string($data, 'actor_id') ?? Claims::requiredString(Claims::object($data, 'act'), 'sub'),
            reason: Claims::requiredString($data, 'reason'),
            scopes: Claims::strings($data, 'scopes'),
            expiresAt: Claims::time($data, 'expires_at'),
            code: Claims::string($data, 'code'),
            redirectUri: Claims::string($data, 'redirect_uri'),
            attributes: $data,
        );
    }
}
