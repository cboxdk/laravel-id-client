<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Role` on the environment plane. */
readonly class Role implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /**
         * `false` marks a STAFF role: an organization's own administrators can never grant
         * it, nor can an invitation carry it. This API can — see the role endpoints.
         */
        public bool $tenantAssignable,
        /** @var list<string> */
        public array $permissions,
        /** The app's manifest key; null for a role made in the console. */
        public ?string $key = null,
        public ?string $description = null,
        /** The app that declared it; null for an app-agnostic role. */
        public ?string $clientId = null,
        /** Set only for a role one organization made for itself. */
        public ?string $organizationId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Role', Value::string(...)),
            name: Field::required($data, 'name', 'Role', Value::string(...)),
            tenantAssignable: Field::required($data, 'tenant_assignable', 'Role', Value::bool(...)),
            permissions: Field::required($data, 'permissions', 'Role', Value::list(Value::string(...))),
            key: Field::optional($data, 'key', 'Role', Value::string(...)),
            description: Field::optional($data, 'description', 'Role', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'Role', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'Role', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'client_id' => $this->clientId,
            'organization_id' => $this->organizationId,
            'tenant_assignable' => $this->tenantAssignable,
            'permissions' => $this->permissions,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
