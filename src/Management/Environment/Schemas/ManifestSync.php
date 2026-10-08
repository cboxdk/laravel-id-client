<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/ManifestSync` on the environment plane. */
readonly class ManifestSync implements JsonSerializable
{
    public function __construct(
        /** The manifest matched what is already stored. */
        public bool $unchanged,
        public int $rolesDeclared,
        public int $permissionsDeclared,
        /**
         * Roles stored here that the manifest no longer declares.
         *
         * @var list<string>
         */
        public array $orphanedRoleKeys,
        /** @var list<string> */
        public array $orphanedPermissionKeys,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            unchanged: Field::required($data, 'unchanged', 'ManifestSync', Value::bool(...)),
            rolesDeclared: Field::required($data, 'roles_declared', 'ManifestSync', Value::int(...)),
            permissionsDeclared: Field::required($data, 'permissions_declared', 'ManifestSync', Value::int(...)),
            orphanedRoleKeys: Field::required($data, 'orphaned_role_keys', 'ManifestSync', Value::list(Value::string(...))),
            orphanedPermissionKeys: Field::required($data, 'orphaned_permission_keys', 'ManifestSync', Value::list(Value::string(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'unchanged' => $this->unchanged,
            'roles_declared' => $this->rolesDeclared,
            'permissions_declared' => $this->permissionsDeclared,
            'orphaned_role_keys' => $this->orphanedRoleKeys,
            'orphaned_permission_keys' => $this->orphanedPermissionKeys,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
