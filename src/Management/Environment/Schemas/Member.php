<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Member` on the environment plane. */
readonly class Member implements JsonSerializable
{
    public function __construct(
        /** The membership's own id. Address a member by `user_id`. */
        public string $id,
        public string $userId,
        public string $organizationId,
        /** One of `owner`, `admin`, `developer`, `member`, `viewer`. */
        public string $role,
        /** One of `active`, `invited`, `suspended`. */
        public string $status,
        public ?string $email = null,
        public ?string $name = null,
        public ?string $joinedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Member', Value::string(...)),
            userId: Field::required($data, 'user_id', 'Member', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'Member', Value::string(...)),
            role: Field::required($data, 'role', 'Member', Value::string(...)),
            status: Field::required($data, 'status', 'Member', Value::string(...)),
            email: Field::optional($data, 'email', 'Member', Value::string(...)),
            name: Field::optional($data, 'name', 'Member', Value::string(...)),
            joinedAt: Field::optional($data, 'joined_at', 'Member', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'role' => $this->role,
            'status' => $this->status,
            'email' => $this->email,
            'name' => $this->name,
            'joined_at' => $this->joinedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
