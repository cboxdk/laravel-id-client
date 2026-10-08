<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A role-conflict (segregation of duties) rule: roles no one person may hold together.
 *
 * `#/components/schemas/SodPolicy` on the environment plane.
 */
readonly class SodPolicy implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** @var list<string> */
        public array $roleIds,
        public bool $active,
        /** null when it binds every organization. */
        public ?string $organizationId = null,
        public ?string $description = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'SodPolicy', Value::string(...)),
            name: Field::required($data, 'name', 'SodPolicy', Value::string(...)),
            roleIds: Field::required($data, 'role_ids', 'SodPolicy', Value::list(Value::string(...))),
            active: Field::required($data, 'active', 'SodPolicy', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'SodPolicy', Value::string(...)),
            description: Field::optional($data, 'description', 'SodPolicy', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'SodPolicy', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'description' => $this->description,
            'role_ids' => $this->roleIds,
            'active' => $this->active,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
