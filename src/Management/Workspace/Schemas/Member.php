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
        /** One of `owner`, `admin`, `developer`, `member`, `viewer`. */
        public ?string $role = null,
        /** One of `active`, `invited`. */
        public ?string $status = null,
        public ?bool $allEnvironments = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::optional($data, 'id', 'Member', Value::string(...)),
            email: Field::optional($data, 'email', 'Member', Value::string(...)),
            name: Field::optional($data, 'name', 'Member', Value::string(...)),
            role: Field::optional($data, 'role', 'Member', Value::string(...)),
            status: Field::optional($data, 'status', 'Member', Value::string(...)),
            allEnvironments: Field::optional($data, 'all_environments', 'Member', Value::bool(...)),
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
            'status' => $this->status,
            'all_environments' => $this->allEnvironments,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
