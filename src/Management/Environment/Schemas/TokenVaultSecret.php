<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A downstream credential in the token vault. Its value is write-only.
 *
 * `#/components/schemas/TokenVaultSecret` on the environment plane.
 */
readonly class TokenVaultSecret implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $provider,
        /** One of `active`, `expired`, `revoked`. */
        public string $status,
        public bool $revoked,
        /** null for the environment's own secrets. */
        public ?string $organizationId = null,
        public ?string $expiresAt = null,
        public ?string $rotatedAt = null,
        public ?string $createdAt = null,
        /**
         * The OAuth client ids that may lease it. Not on lists.
         *
         * @var list<string>
         */
        public ?array $grants = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'TokenVaultSecret', Value::string(...)),
            name: Field::required($data, 'name', 'TokenVaultSecret', Value::string(...)),
            provider: Field::required($data, 'provider', 'TokenVaultSecret', Value::string(...)),
            status: Field::required($data, 'status', 'TokenVaultSecret', Value::string(...)),
            revoked: Field::required($data, 'revoked', 'TokenVaultSecret', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'TokenVaultSecret', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'TokenVaultSecret', Value::string(...)),
            rotatedAt: Field::optional($data, 'rotated_at', 'TokenVaultSecret', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'TokenVaultSecret', Value::string(...)),
            grants: Field::optional($data, 'grants', 'TokenVaultSecret', Value::list(Value::string(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'provider' => $this->provider,
            'status' => $this->status,
            'revoked' => $this->revoked,
            'expires_at' => $this->expiresAt,
            'rotated_at' => $this->rotatedAt,
            'created_at' => $this->createdAt,
            'grants' => $this->grants,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
