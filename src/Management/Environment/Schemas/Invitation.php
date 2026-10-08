<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Invitation` on the environment plane. */
readonly class Invitation implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $email,
        /** One of `owner`, `admin`, `developer`, `member`, `viewer`. */
        public string $role,
        /**
         * Ids of the access roles granted when the invitation is accepted.
         *
         * @var list<string>
         */
        public array $roles,
        /** One of `pending`, `accepted`, `revoked`. */
        public string $status,
        /** The app it came from. */
        public ?string $clientId = null,
        /** Where the person is sent after accepting. */
        public ?string $returnTo = null,
        public ?string $invitedAt = null,
        public ?string $expiresAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Invitation', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'Invitation', Value::string(...)),
            email: Field::required($data, 'email', 'Invitation', Value::string(...)),
            role: Field::required($data, 'role', 'Invitation', Value::string(...)),
            roles: Field::required($data, 'roles', 'Invitation', Value::list(Value::string(...))),
            status: Field::required($data, 'status', 'Invitation', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'Invitation', Value::string(...)),
            returnTo: Field::optional($data, 'return_to', 'Invitation', Value::string(...)),
            invitedAt: Field::optional($data, 'invited_at', 'Invitation', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'Invitation', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'email' => $this->email,
            'role' => $this->role,
            'roles' => $this->roles,
            'status' => $this->status,
            'client_id' => $this->clientId,
            'return_to' => $this->returnTo,
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
