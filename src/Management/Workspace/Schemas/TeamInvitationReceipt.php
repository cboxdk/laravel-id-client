<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/TeamInvitationReceipt` on the workspace plane. */
readonly class TeamInvitationReceipt implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
        /** @var list<string> */
        public array $roles,
        /** One of `invited`. */
        public string $status,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'TeamInvitationReceipt', Value::string(...)),
            email: Field::required($data, 'email', 'TeamInvitationReceipt', Value::string(...)),
            roles: Field::required($data, 'roles', 'TeamInvitationReceipt', Value::list(Value::string(...))),
            status: Field::required($data, 'status', 'TeamInvitationReceipt', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->roles,
            'status' => $this->status,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
