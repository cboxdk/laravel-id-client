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
        /** One of `splunk_hec`, `elastic_ecs`, `graylog_gelf`, `cef_http`, `generic_json`. */
        public string $destination,
        public string $endpointUrl,
        /** One of `none`, `bearer`, `splunk`, `hmac`. */
        public string $auth,
        public bool $enabled,
        /** null when the environment owns it: it carries EVERY organization's entries. */
        public ?string $organizationId = null,
        public ?int $consecutiveFailures = null,
        public ?string $lastSuccessAt = null,
        public ?string $createdAt = null,
        /** A generated HMAC key, on the create answer only — shown once. `null` on an idempotent replay. */
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
            organizationId: Field::optional($data, 'organization_id', 'LogStream', Value::string(...)),
            consecutiveFailures: Field::optional($data, 'consecutive_failures', 'LogStream', Value::int(...)),
            lastSuccessAt: Field::optional($data, 'last_success_at', 'LogStream', Value::string(...)),
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
            'organization_id' => $this->organizationId,
            'enabled' => $this->enabled,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_success_at' => $this->lastSuccessAt,
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
