<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A directory syncing an organization's people in. Its token and credentials are never returned after they are minted.
 *
 * `#/components/schemas/Directory` on the environment plane.
 */
readonly class Directory implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $name,
        /** One of `scim`, `google_workspace`, `microsoft_entra`. */
        public string $provider,
        /** true when this platform fetches from the provider; false for a SCIM endpoint the provider posts to. */
        public bool $pull,
        public bool $active,
        /** One of `active`, `paused`. */
        public string $status,
        /** Where the identity provider sends SCIM requests. null for a pull directory. */
        public ?string $scimBaseUrl = null,
        public ?string $lastSyncedAt = null,
        public ?string $lastSyncError = null,
        public ?string $createdAt = null,
        /**
         * The SCIM bearer token, on the create and rotate answers only — shown once, never
         * retrievable again. `null` on an idempotent replay of that answer.
         */
        public ?string $bearerToken = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Directory', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'Directory', Value::string(...)),
            name: Field::required($data, 'name', 'Directory', Value::string(...)),
            provider: Field::required($data, 'provider', 'Directory', Value::string(...)),
            pull: Field::required($data, 'pull', 'Directory', Value::bool(...)),
            active: Field::required($data, 'active', 'Directory', Value::bool(...)),
            status: Field::required($data, 'status', 'Directory', Value::string(...)),
            scimBaseUrl: Field::optional($data, 'scim_base_url', 'Directory', Value::string(...)),
            lastSyncedAt: Field::optional($data, 'last_synced_at', 'Directory', Value::string(...)),
            lastSyncError: Field::optional($data, 'last_sync_error', 'Directory', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'Directory', Value::string(...)),
            bearerToken: Field::optional($data, 'bearer_token', 'Directory', Value::string(...)),
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
            'pull' => $this->pull,
            'active' => $this->active,
            'status' => $this->status,
            'scim_base_url' => $this->scimBaseUrl,
            'last_synced_at' => $this->lastSyncedAt,
            'last_sync_error' => $this->lastSyncError,
            'created_at' => $this->createdAt,
            'bearer_token' => $this->bearerToken,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
