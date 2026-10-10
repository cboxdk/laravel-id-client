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
        /** One of `scim`, `google_workspace`, `microsoft_entra`, `workday`, `bamboohr`, `rippling`, `hibob`, `personio`. */
        public string $provider,
        /** true when this platform fetches from the provider; false for a SCIM endpoint the provider posts to. */
        public bool $pull,
        public bool $active,
        /** One of `active`, `paused`. */
        public string $status,
        /** true for an HR system (Workday, BambooHR, Rippling, HiBob, Personio): employment dates decide access and departments become groups. */
        public ?bool $hris = null,
        /** Where the identity provider sends SCIM requests. null for a pull directory. */
        public ?string $scimBaseUrl = null,
        public ?string $lastSyncedAt = null,
        /** Why the last pull failed, or for a partial one how many records could not be synced and the first reason. Never names a person. */
        public ?string $lastSyncError = null,
        /** When the last pull started. null for a SCIM directory. */
        public ?string $lastSyncStartedAt = null,
        /**
         * How the last pull went. partial: the provider answered but some records could not be reconciled, or a mass deprovisioning was refused.
         * One of `running`, `succeeded`, `partial`, `failed`.
         */
        public ?string $lastSyncStatus = null,
        /** The last pull's counts, and up to 50 records it could not reconcile — by the provider's own id, never by name or email. */
        public ?DirectoryLastSyncStats $lastSyncStats = null,
        /** Minutes between scheduled pulls. null for a SCIM directory. */
        public ?int $syncIntervalMinutes = null,
        public ?string $nextSyncAt = null,
        /**
         * HR systems only: the HR system's own field names copied onto each person.
         *
         * @var list<string>|null
         */
        public ?array $customAttributes = null,
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
            hris: Field::optional($data, 'hris', 'Directory', Value::bool(...)),
            scimBaseUrl: Field::optional($data, 'scim_base_url', 'Directory', Value::string(...)),
            lastSyncedAt: Field::optional($data, 'last_synced_at', 'Directory', Value::string(...)),
            lastSyncError: Field::optional($data, 'last_sync_error', 'Directory', Value::string(...)),
            lastSyncStartedAt: Field::optional($data, 'last_sync_started_at', 'Directory', Value::string(...)),
            lastSyncStatus: Field::optional($data, 'last_sync_status', 'Directory', Value::string(...)),
            lastSyncStats: Field::optional($data, 'last_sync_stats', 'Directory', Value::dto(DirectoryLastSyncStats::fromArray(...))),
            syncIntervalMinutes: Field::optional($data, 'sync_interval_minutes', 'Directory', Value::int(...)),
            nextSyncAt: Field::optional($data, 'next_sync_at', 'Directory', Value::string(...)),
            customAttributes: Field::optional($data, 'custom_attributes', 'Directory', Value::list(Value::string(...))),
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
            'hris' => $this->hris,
            'active' => $this->active,
            'status' => $this->status,
            'scim_base_url' => $this->scimBaseUrl,
            'last_synced_at' => $this->lastSyncedAt,
            'last_sync_error' => $this->lastSyncError,
            'last_sync_started_at' => $this->lastSyncStartedAt,
            'last_sync_status' => $this->lastSyncStatus,
            'last_sync_stats' => $this->lastSyncStats?->toArray(),
            'sync_interval_minutes' => $this->syncIntervalMinutes,
            'next_sync_at' => $this->nextSyncAt,
            'custom_attributes' => $this->customAttributes,
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
