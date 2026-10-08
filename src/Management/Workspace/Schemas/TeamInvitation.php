<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/TeamInvitation` on the workspace plane. */
readonly class TeamInvitation implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
        /** One of `admin`, `developer`, `member`, `viewer`. */
        public string $role,
        public string $expiresAt,
        /** The person, or the API key, that sent it — by name. */
        public ?string $invitedBy = null,
        public ?string $invitedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'TeamInvitation', Value::string(...)),
            email: Field::required($data, 'email', 'TeamInvitation', Value::string(...)),
            role: Field::required($data, 'role', 'TeamInvitation', Value::string(...)),
            expiresAt: Field::required($data, 'expires_at', 'TeamInvitation', Value::string(...)),
            invitedBy: Field::optional($data, 'invited_by', 'TeamInvitation', Value::string(...)),
            invitedAt: Field::optional($data, 'invited_at', 'TeamInvitation', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'invited_by' => $this->invitedBy,
            'invited_at' => $this->invitedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
