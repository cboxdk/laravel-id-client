<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/PersonalApiKey` on the account plane. */
readonly class PersonalApiKey implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $clientId,
        /** @var list<string> */
        public array $permissions,
        public ?string $name = null,
        public ?string $prefix = null,
        public ?string $expiresAt = null,
        public ?string $createdAt = null,
        /** The key's value — shown once, on creation; null on an idempotent replay. */
        public ?string $token = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PersonalApiKey', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'PersonalApiKey', Value::string(...)),
            clientId: Field::required($data, 'client_id', 'PersonalApiKey', Value::string(...)),
            permissions: Field::required($data, 'permissions', 'PersonalApiKey', Value::list(Value::string(...))),
            name: Field::optional($data, 'name', 'PersonalApiKey', Value::string(...)),
            prefix: Field::optional($data, 'prefix', 'PersonalApiKey', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'PersonalApiKey', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'PersonalApiKey', Value::string(...)),
            token: Field::optional($data, 'token', 'PersonalApiKey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'organization_id' => $this->organizationId,
            'client_id' => $this->clientId,
            'prefix' => $this->prefix,
            'permissions' => $this->permissions,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
            'token' => $this->token,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
