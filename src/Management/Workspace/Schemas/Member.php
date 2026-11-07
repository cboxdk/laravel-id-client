<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Member` on the workspace plane. */
readonly class Member implements JsonSerializable
{
    public function __construct(
        public ?string $id = null,
        public ?string $email = null,
        public ?string $name = null,
        /**
         * Shared membership tier; only current owner implies app authority.
         * One of `owner`, `admin`, `developer`, `member`, `viewer`.
         */
        public ?string $role = null,
        /** @var list<string> */
        public ?array $roles = null,
        public ?string $rolesVersion = null,
        /** One of `active`, `invited`, `suspended`. */
        public ?string $status = null,
        public ?bool $allEnvironments = null,
        /** @var list<string> */
        public ?array $environmentIds = null,
        public ?string $environmentAccessVersion = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::optional($data, 'id', 'Member', Value::string(...)),
            email: Field::optional($data, 'email', 'Member', Value::string(...)),
            name: Field::optional($data, 'name', 'Member', Value::string(...)),
            role: Field::optional($data, 'role', 'Member', Value::string(...)),
            roles: Field::optional($data, 'roles', 'Member', Value::list(Value::string(...))),
            rolesVersion: Field::optional($data, 'roles_version', 'Member', Value::string(...)),
            status: Field::optional($data, 'status', 'Member', Value::string(...)),
            allEnvironments: Field::optional($data, 'all_environments', 'Member', Value::bool(...)),
            environmentIds: Field::optional($data, 'environment_ids', 'Member', Value::list(Value::string(...))),
            environmentAccessVersion: Field::optional($data, 'environment_access_version', 'Member', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'roles' => $this->roles,
            'roles_version' => $this->rolesVersion,
            'status' => $this->status,
            'all_environments' => $this->allEnvironments,
            'environment_ids' => $this->environmentIds,
            'environment_access_version' => $this->environmentAccessVersion,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
