<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Contracts\Principal;
use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

/**
 * A support session: a member of staff acting as a customer in one app, for a stated
 * reason, for at most an hour. Tokens minted for it carry `act` (see
 * {@see Principal::actor()}) and no refresh token.
 *
 * `url` is where to send the member of staff to start it, when Cbox ID returns one.
 */
readonly class SupportSession
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public ?string $userId = null,
        public ?string $organizationId = null,
        public ?string $clientId = null,
        public ?string $actorSubject = null,
        public ?string $reason = null,
        public ?string $url = null,
        public ?DateTimeImmutable $expiresAt = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            userId: Claims::string($data, 'user_id'),
            organizationId: Claims::string($data, 'organization_id'),
            clientId: Claims::string($data, 'client_id'),
            actorSubject: Claims::string($data, 'actor_id') ?? Claims::string(Claims::object($data, 'act'), 'sub'),
            reason: Claims::string($data, 'reason'),
            url: Claims::string($data, 'url'),
            expiresAt: Claims::time($data, 'expires_at'),
            attributes: $data,
        );
    }
}
