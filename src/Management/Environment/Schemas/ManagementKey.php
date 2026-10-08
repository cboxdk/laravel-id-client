<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A management key of this environment. `token` is present once, on the answer that minted it, and never again.
 *
 * `#/components/schemas/ManagementKey` on the environment plane.
 */
readonly class ManagementKey implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** The key's first characters, to recognise it by. */
        public string $prefix,
        /** @var list<string> */
        public array $scopes,
        public bool $active,
        public ?string $description = null,
        /** The key that minted this one, if a key did. */
        public ?string $parentKeyId = null,
        /** The key this one replaced, if it came from a rotation. */
        public ?string $rotatedFromId = null,
        /** The approval policy: actions held for the person behind the key. */
        public ?ManagementKeyRequireApproval $requireApproval = null,
        public ?ManagementKeyCreatedBy $createdBy = null,
        public ?string $expiresAt = null,
        public ?string $lastUsedAt = null,
        public ?string $revokedAt = null,
        public ?string $createdAt = null,
        /** The key's value. Only on the answer that minted it; null on an idempotent replay of that answer, because the value is never stored. */
        public ?string $token = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'ManagementKey', Value::string(...)),
            name: Field::required($data, 'name', 'ManagementKey', Value::string(...)),
            prefix: Field::required($data, 'prefix', 'ManagementKey', Value::string(...)),
            scopes: Field::required($data, 'scopes', 'ManagementKey', Value::list(Value::string(...))),
            active: Field::required($data, 'active', 'ManagementKey', Value::bool(...)),
            description: Field::optional($data, 'description', 'ManagementKey', Value::string(...)),
            parentKeyId: Field::optional($data, 'parent_key_id', 'ManagementKey', Value::string(...)),
            rotatedFromId: Field::optional($data, 'rotated_from_id', 'ManagementKey', Value::string(...)),
            requireApproval: Field::optional($data, 'require_approval', 'ManagementKey', Value::dto(ManagementKeyRequireApproval::fromArray(...))),
            createdBy: Field::optional($data, 'created_by', 'ManagementKey', Value::dto(ManagementKeyCreatedBy::fromArray(...))),
            expiresAt: Field::optional($data, 'expires_at', 'ManagementKey', Value::string(...)),
            lastUsedAt: Field::optional($data, 'last_used_at', 'ManagementKey', Value::string(...)),
            revokedAt: Field::optional($data, 'revoked_at', 'ManagementKey', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'ManagementKey', Value::string(...)),
            token: Field::optional($data, 'token', 'ManagementKey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'prefix' => $this->prefix,
            'scopes' => $this->scopes,
            'active' => $this->active,
            'parent_key_id' => $this->parentKeyId,
            'rotated_from_id' => $this->rotatedFromId,
            'require_approval' => $this->requireApproval?->toArray(),
            'created_by' => $this->createdBy?->toArray(),
            'expires_at' => $this->expiresAt,
            'last_used_at' => $this->lastUsedAt,
            'revoked_at' => $this->revokedAt,
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
