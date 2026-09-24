<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Support\Claims;

/**
 * A role in this environment.
 *
 * `key` is the manifest key of a role an app declared, and null for one made in the
 * console. `clientId` is the app that declared it (null for an app-agnostic role);
 * `organizationId` is set only for a role one organization made for itself.
 * `tenantAssignable: false` is a STAFF role: an organization's own administrators can
 * never grant it; this API can ({@see Management::grantEnvironmentRole()}).
 */
readonly class Role
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public ?string $key,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $clientId = null,
        public bool $tenantAssignable = true,
        public array $permissions = [],
        public array $attributes = [],
        public ?string $organizationId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            key: Claims::string($data, 'key'),
            name: Claims::string($data, 'name'),
            description: Claims::string($data, 'description'),
            clientId: Claims::string($data, 'client_id'),
            tenantAssignable: Claims::bool($data, 'tenant_assignable', true),
            permissions: Claims::strings($data, 'permissions'),
            attributes: $data,
            organizationId: Claims::string($data, 'organization_id'),
        );
    }
}
