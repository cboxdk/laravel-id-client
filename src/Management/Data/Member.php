<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Enums\OrganizationRole;
use Cbox\Id\Client\Support\Claims;
use DateTimeImmutable;

/**
 * A person's membership of an organization. Address it by `userId`; `membershipId` is
 * the membership's own id. `role` is the built-in tier (null for one this SDK does not
 * know); `status` is `active`, `invited` or `suspended`.
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
        public ?string $membershipId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $role = Claims::string($data, 'role');

        return new self(
            userId: Claims::requiredString($data, 'user_id'),
            role: $role !== null ? OrganizationRole::tryFrom($role) : null,
            organizationId: Claims::string($data, 'organization_id'),
            email: Claims::string($data, 'email'),
            name: Claims::string($data, 'name'),
            status: Claims::string($data, 'status'),
            joinedAt: Claims::time($data, 'joined_at') ?? Claims::time($data, 'created_at'),
            attributes: $data,
            membershipId: Claims::string($data, 'id'),
        );
    }
}
