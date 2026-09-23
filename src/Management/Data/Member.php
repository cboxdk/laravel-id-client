<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

/**
 * A person's membership of an organization. `role` is the built-in tier; null when the
 * instance reported a tier this SDK does not know.
 */
readonly class Member
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $userId,
        public ?OrganizationRole $role,
        public ?string $organizationId = null,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $status = null,
        public ?DateTimeImmutable $joinedAt = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $role = Claims::string($data, 'role');

        return new self(
            userId: Claims::string($data, 'user_id') ?? Claims::requiredString($data, 'id'),
            role: $role !== null ? OrganizationRole::tryFrom($role) : null,
            organizationId: Claims::string($data, 'organization_id'),
            email: Claims::string($data, 'email'),
            name: Claims::string($data, 'name'),
            status: Claims::string($data, 'status'),
            joinedAt: Claims::time($data, 'joined_at') ?? Claims::time($data, 'created_at'),
            attributes: $data,
        );
    }
}
