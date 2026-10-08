<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A downstream app people are pushed to over SCIM. Its credential is write-only.
 *
 * `#/components/schemas/ProvisioningTarget` on the environment plane.
 */
readonly class ProvisioningTarget implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $baseUrl,
        /** One of `bearer`, `oauth2_client_credentials`. */
        public string $authScheme,
        /** false while paused. */
        public bool $active,
        /** null when it receives every organization's people. */
        public ?string $organizationId = null,
        public ?string $tokenUrl = null,
        public ?string $clientId = null,
        public ?int $consecutiveFailures = null,
        public ?string $lastSuccessAt = null,
        public ?string $lastError = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'ProvisioningTarget', Value::string(...)),
            name: Field::required($data, 'name', 'ProvisioningTarget', Value::string(...)),
            baseUrl: Field::required($data, 'base_url', 'ProvisioningTarget', Value::string(...)),
            authScheme: Field::required($data, 'auth_scheme', 'ProvisioningTarget', Value::string(...)),
            active: Field::required($data, 'active', 'ProvisioningTarget', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'ProvisioningTarget', Value::string(...)),
            tokenUrl: Field::optional($data, 'token_url', 'ProvisioningTarget', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'ProvisioningTarget', Value::string(...)),
            consecutiveFailures: Field::optional($data, 'consecutive_failures', 'ProvisioningTarget', Value::int(...)),
            lastSuccessAt: Field::optional($data, 'last_success_at', 'ProvisioningTarget', Value::string(...)),
            lastError: Field::optional($data, 'last_error', 'ProvisioningTarget', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'ProvisioningTarget', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'base_url' => $this->baseUrl,
            'auth_scheme' => $this->authScheme,
            'token_url' => $this->tokenUrl,
            'client_id' => $this->clientId,
            'active' => $this->active,
            'consecutive_failures' => $this->consecutiveFailures,
            'last_success_at' => $this->lastSuccessAt,
            'last_error' => $this->lastError,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
