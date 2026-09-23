<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;

/**
 * A role held by a person — in one organization, or environment-wide when
 * `organizationId` is null.
 */
readonly class RoleAssignment
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $roleId,
        public ?string $key = null,
        public ?string $name = null,
        public ?string $organizationId = null,
        public ?string $userId = null,
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            roleId: Claims::string($data, 'role_id') ?? Claims::requiredString($data, 'id'),
            key: Claims::string($data, 'key'),
            name: Claims::string($data, 'name'),
            organizationId: Claims::string($data, 'organization_id'),
            userId: Claims::string($data, 'user_id'),
            attributes: $data,
        );
    }
}
