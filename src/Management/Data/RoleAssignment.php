<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Support\Claims;

/**
 * A role held by a person — in one organization, or environment-wide (staff) when
 * `organizationId` is null. `source` is `manual`, `pushed` (by a directory) or `system`.
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
        public ?string $clientId = null,
        public bool $tenantAssignable = true,
        public ?string $source = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            roleId: Claims::requiredString($data, 'role_id'),
            key: Claims::string($data, 'key'),
            name: Claims::string($data, 'name'),
            organizationId: Claims::string($data, 'organization_id'),
            userId: Claims::string($data, 'user_id'),
            attributes: $data,
            clientId: Claims::string($data, 'client_id'),
            tenantAssignable: Claims::bool($data, 'tenant_assignable', true),
            source: Claims::string($data, 'source'),
        );
    }
}
