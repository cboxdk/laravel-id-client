<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Support\Claims;
use Cbox\Id\Client\Support\PublicId;
use DateTimeImmutable;

/**
 * A fresh access token for one person's connected account at a provider (Pipes). Use it,
 * drop it, and lease again next time — never store it.
 */
readonly class PipeToken
{
    /**
     * @param  list<string>  $scopes  what the person consented to
     * @param  array<string, string>  $metadata  what some providers need to be called at all —
     *                                           Salesforce's `instance_url`, Slack's `team.id`
     */
    public function __construct(
        /** The provider's access token. Send it as a bearer token to the provider's API. */
        public string $accessToken,
        public string $provider,
        public string $userId,
        public string $connectionId,
        public array $scopes,
        /** When the PROVIDER stops accepting it; null for tokens that do not expire. */
        public ?DateTimeImmutable $expiresAt,
        /** When to drop it and lease again. */
        public ?DateTimeImmutable $leaseExpiresAt,
        public array $metadata = [],
        public string $tokenType = 'Bearer',
    ) {}

    /**
     * The ids are the raw ones a token carries (`userId` is the person's `sub`), on either
     * API: the `/v1` API answers `usr_01J…`, `{issuer}/api/v1` the bare ULID.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $metadata = [];

        foreach (Claims::object($data, 'metadata') as $key => $value) {
            if (is_string($value)) {
                $metadata[$key] = $value;
            }
        }

        return new self(
            accessToken: Claims::requiredString($data, 'access_token'),
            provider: Claims::string($data, 'provider') ?? '',
            userId: PublicId::raw(Claims::string($data, 'user_id') ?? ''),
            connectionId: PublicId::raw(Claims::string($data, 'connection_id') ?? ''),
            scopes: is_array($data['scopes'] ?? null) ? Claims::strings($data, 'scopes') : [],
            expiresAt: Claims::time($data, 'expires_at'),
            leaseExpiresAt: Claims::time($data, 'lease_expires_at'),
            metadata: $metadata,
        );
    }
}
