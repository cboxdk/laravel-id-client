<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Enums\ApiKeyStatus;
use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

/**
 * A customer API key, as listed for an organization. Never the key itself — Cbox ID shows
 * that once, to the person who created it. Revoked and expired keys are listed too;
 * `status` says which.
 */
readonly class ApiKey
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $prefix = null,
        public ?string $organizationId = null,
        public ?string $userId = null,
        public ?string $clientId = null,
        public array $permissions = [],
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?DateTimeImmutable $lastUsedAt = null,
        public bool $revoked = false,
        public array $attributes = [],
        public ?ApiKeyStatus $status = null,
        public ?DateTimeImmutable $revokedAt = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $status = Claims::string($data, 'status');

        return new self(
            id: Claims::requiredString($data, 'id'),
            name: Claims::string($data, 'name'),
            prefix: Claims::string($data, 'prefix'),
            organizationId: Claims::string($data, 'organization_id'),
            userId: Claims::string($data, 'user_id'),
            clientId: Claims::string($data, 'client_id'),
            permissions: Claims::strings($data, 'permissions'),
            createdAt: Claims::time($data, 'created_at'),
            expiresAt: Claims::time($data, 'expires_at'),
            lastUsedAt: Claims::time($data, 'last_used_at'),
            revoked: Claims::bool($data, 'revoked') || Claims::time($data, 'revoked_at') !== null,
            attributes: $data,
            status: $status !== null ? ApiKeyStatus::tryFrom($status) : null,
            revokedAt: Claims::time($data, 'revoked_at'),
        );
    }

    /** Whether it still verifies — `status` when the instance sent it. */
    public function isActive(): bool
    {
        return $this->status !== null ? $this->status === ApiKeyStatus::Active : ! $this->revoked;
    }
}
