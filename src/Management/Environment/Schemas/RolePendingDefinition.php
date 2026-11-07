<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A published change waiting for an environment administrator to explicitly activate this version.
 *
 * `RolePendingDefinition` on the environment plane.
 */
readonly class RolePendingDefinition implements JsonSerializable
{
    public function __construct(
        public string $version,
        /** @var list<string> */
        public array $permissions,
        public bool $tenantAssignable,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            version: Field::required($data, 'version', 'RolePendingDefinition', Value::string(...)),
            permissions: Field::required($data, 'permissions', 'RolePendingDefinition', Value::list(Value::string(...))),
            tenantAssignable: Field::required($data, 'tenant_assignable', 'RolePendingDefinition', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'permissions' => $this->permissions,
            'tenant_assignable' => $this->tenantAssignable,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
