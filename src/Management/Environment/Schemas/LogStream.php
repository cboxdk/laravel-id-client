<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/LogStream` on the environment plane. */
readonly class LogStream implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** One of `splunk_hec`, `elastic_ecs`, `graylog_gelf`, `cef_http`, `generic_json`, `datadog`, `s3`, `gcs`. */
        public string $destination,
        /** Where entries go. For Datadog, S3 and GCS, derived from the site or region unless a custom endpoint was given. */
        public string $endpointUrl,
        /**
         * An HTTP collector's scheme; `none` for the cloud destinations, which authenticate their own way.
         * One of `none`, `bearer`, `splunk`, `hmac`.
         */
        public string $auth,
        public bool $enabled,
        /**
         * `degraded`: recent transient failures, still retrying. `paused`: the circuit is open after repeated failures and resumes on its own. `action_required`: the destination refused the credential or the settings — update the stream, or run a successful test.
         * One of `healthy`, `degraded`, `paused`, `action_required`.
         */
        public string $health,
        /** Datadog, S3 and GCS: the destination's settings, as stored. Never a credential — the API key, secret access key or service-account key is the encrypted secret. null for an HTTP collector. */
        public ?LogStreamOptions $options = null,
        /** An assumed-role S3 stream's external ID, generated for it: the role's trust policy must require it (`sts:ExternalId`). null otherwise. */
        public ?string $externalId = null,
        /** null when the environment owns it: it carries EVERY organization's entries. */
        public ?string $organizationId = null,
        public ?int $consecutiveFailures = null,
        public ?string $lastSuccessAt = null,
        /** The latest failure, scrubbed of the stream's secret. */
        public ?string $lastError = null,
        /** One of `transient`, `authentication`, `configuration`. */
        public ?string $lastFailureKind = null,
        public ?string $lastFailureAt = null,
        public ?string $createdAt = null,
        /** A generated HMAC key, on the answer that generated it only — shown once. `null` on an idempotent replay. A credential the caller supplied is never echoed. */
        public ?string $secret = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'LogStream', Value::string(...)),
            name: Field::required($data, 'name', 'LogStream', Value::string(...)),
            destination: Field::required($data, 'destination', 'LogStream', Value::string(...)),
            endpointUrl: Field::required($data, 'endpoint_url', 'LogStream', Value::string(...)),
            auth: Field::required($data, 'auth', 'LogStream', Value::string(...)),
            enabled: Field::required($data, 'enabled', 'LogStream', Value::bool(...)),
            health: Field::required($data, 'health', 'LogStream', Value::string(...)),
            options: Field::optional($data, 'options', 'LogStream', Value::dto(LogStreamOptions::fromArray(...))),
            externalId: Field::optional($data, 'external_id', 'LogStream', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'LogStream', Value::string(...)),
            consecutiveFailures: Field::optional($data, 'consecutive_failures', 'LogStream', Value::int(...)),
            lastSuccessAt: Field::optional($data, 'last_success_at', 'LogStream', Value::string(...)),
            lastError: Field::optional($data, 'last_error', 'LogStream', Value::string(...)),
            lastFailureKind: Field::optional($data, 'last_failure_kind', 'LogStream', Value::string(...)),
            lastFailureAt: Field::optional($data, 'last_failure_at', 'LogStream', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'LogStream', Value::string(...)),
            secret: Field::optional($data, 'secret', 'LogStream', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'destination' => $this->destination,
            'endpoint_url' => $this->endpointUrl,
            'auth' => $this->auth,
            'options' => $this->options?->toArray(),
            'external_id' => $this->externalId,
            'organization_id' => $this->organizationId,
            'enabled' => $this->enabled,
            'health' => $this->health,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_success_at' => $this->lastSuccessAt,
            'last_error' => $this->lastError,
            'last_failure_kind' => $this->lastFailureKind,
            'last_failure_at' => $this->lastFailureAt,
            'created_at' => $this->createdAt,
            'secret' => $this->secret,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
