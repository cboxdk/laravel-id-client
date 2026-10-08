<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/RoleAssignment` on the environment plane. */
readonly class RoleAssignment implements JsonSerializable
{
    public function __construct(
        public string $roleId,
        public string $name,
        public string $userId,
        /** One of `manual`, `pushed`, `system`. */
        public string $source,
        public ?string $key = null,
        public ?string $clientId = null,
        public ?bool $tenantAssignable = null,
        /** The organization it is held in; null for an environment-wide (staff) grant. */
        public ?string $organizationId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            roleId: Field::required($data, 'role_id', 'RoleAssignment', Value::string(...)),
            name: Field::required($data, 'name', 'RoleAssignment', Value::string(...)),
            userId: Field::required($data, 'user_id', 'RoleAssignment', Value::string(...)),
            source: Field::required($data, 'source', 'RoleAssignment', Value::string(...)),
            key: Field::optional($data, 'key', 'RoleAssignment', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'RoleAssignment', Value::string(...)),
            tenantAssignable: Field::optional($data, 'tenant_assignable', 'RoleAssignment', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'RoleAssignment', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'role_id' => $this->roleId,
            'key' => $this->key,
            'name' => $this->name,
            'client_id' => $this->clientId,
            'tenant_assignable' => $this->tenantAssignable,
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'source' => $this->source,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
