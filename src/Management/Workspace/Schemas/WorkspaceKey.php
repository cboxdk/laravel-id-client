<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A workspace key (`cbid_ws_…`). `token` is present only in the answer that minted it, and `null` on an idempotent replay.
 *
 * `#/components/schemas/WorkspaceKey` on the workspace plane.
 */
readonly class WorkspaceKey implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** One of `owner`, `admin`, `developer`, `member`, `viewer`. */
        public string $role,
        public ?string $prefix = null,
        /**
         * Null when the role alone bounds the key.
         *
         * @var list<string>|null
         */
        public ?array $scopes = null,
        /** The key that minted it, if a key did. Revoking the parent revokes it. */
        public ?string $parentKeyId = null,
        /** `organization_member` or `workspace_key`; null for a key minted before this was recorded. */
        public ?string $createdByType = null,
        public ?string $expiresAt = null,
        public ?string $revokedAt = null,
        public ?string $lastUsedAt = null,
        /** The key's value. Shown once. */
        public ?string $token = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'WorkspaceKey', Value::string(...)),
            name: Field::required($data, 'name', 'WorkspaceKey', Value::string(...)),
            role: Field::required($data, 'role', 'WorkspaceKey', Value::string(...)),
            prefix: Field::optional($data, 'prefix', 'WorkspaceKey', Value::string(...)),
            scopes: Field::optional($data, 'scopes', 'WorkspaceKey', Value::list(Value::string(...))),
            parentKeyId: Field::optional($data, 'parent_key_id', 'WorkspaceKey', Value::string(...)),
            createdByType: Field::optional($data, 'created_by_type', 'WorkspaceKey', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'WorkspaceKey', Value::string(...)),
            revokedAt: Field::optional($data, 'revoked_at', 'WorkspaceKey', Value::string(...)),
            lastUsedAt: Field::optional($data, 'last_used_at', 'WorkspaceKey', Value::string(...)),
            token: Field::optional($data, 'token', 'WorkspaceKey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'role' => $this->role,
            'scopes' => $this->scopes,
            'parent_key_id' => $this->parentKeyId,
            'created_by_type' => $this->createdByType,
            'expires_at' => $this->expiresAt,
            'revoked_at' => $this->revokedAt,
            'last_used_at' => $this->lastUsedAt,
            'token' => $this->token,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
