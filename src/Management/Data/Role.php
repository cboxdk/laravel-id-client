<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Data;

use Cbox\Id\Client\Contracts\Management;
use Cbox\Id\Client\Support\Claims;

/**
 * A role in this environment — usually one an app declared in its manifest.
 *
 * `tenantAssignable: false` is a staff-only role: it can be granted environment-wide
 * ({@see Management::grantEnvironmentRole()}) but never inside
 * a customer's organization. `clientId` is the app that declared it; null for a role
 * shared by every app.
 */
readonly class Role
{
    /**
     * @param  list<string>  $permissions
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public string $id,
        public string $key,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $clientId = null,
        public bool $tenantAssignable = true,
        public array $permissions = [],
        public array $attributes = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Claims::requiredString($data, 'id'),
            key: Claims::string($data, 'key') ?? Claims::requiredString($data, 'slug'),
            name: Claims::string($data, 'name'),
            description: Claims::string($data, 'description'),
            clientId: Claims::string($data, 'client_id'),
            tenantAssignable: Claims::bool($data, 'tenant_assignable', true),
            permissions: Claims::strings($data, 'permissions'),
            attributes: $data,
        );
    }
}
