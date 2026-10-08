<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/ApiKey` on the environment plane. */
readonly class ApiKey implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** What a person recognises the key by. Never the key. */
        public string $prefix,
        public string $organizationId,
        /** Its holder. */
        public string $userId,
        /** The app whose API it is for. */
        public string $clientId,
        /** @var list<string> */
        public array $permissions,
        /** One of `active`, `expired`, `revoked`. */
        public string $status,
        public bool $revoked,
        public ?string $name = null,
        public ?string $createdAt = null,
        public ?string $expiresAt = null,
        public ?string $lastUsedAt = null,
        public ?string $revokedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'ApiKey', Value::string(...)),
            prefix: Field::required($data, 'prefix', 'ApiKey', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'ApiKey', Value::string(...)),
            userId: Field::required($data, 'user_id', 'ApiKey', Value::string(...)),
            clientId: Field::required($data, 'client_id', 'ApiKey', Value::string(...)),
            permissions: Field::required($data, 'permissions', 'ApiKey', Value::list(Value::string(...))),
            status: Field::required($data, 'status', 'ApiKey', Value::string(...)),
            revoked: Field::required($data, 'revoked', 'ApiKey', Value::bool(...)),
            name: Field::optional($data, 'name', 'ApiKey', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'ApiKey', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'ApiKey', Value::string(...)),
            lastUsedAt: Field::optional($data, 'last_used_at', 'ApiKey', Value::string(...)),
            revokedAt: Field::optional($data, 'revoked_at', 'ApiKey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'client_id' => $this->clientId,
            'permissions' => $this->permissions,
            'status' => $this->status,
            'revoked' => $this->revoked,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'last_used_at' => $this->lastUsedAt,
            'revoked_at' => $this->revokedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
