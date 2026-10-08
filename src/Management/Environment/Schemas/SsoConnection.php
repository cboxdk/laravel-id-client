<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A SAML or OIDC connection an organization signs in through. Its certificate, client secret and signing key are write-only.
 *
 * `#/components/schemas/SsoConnection` on the environment plane.
 */
readonly class SsoConnection implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** One of `saml`, `oidc`, `oauth2`. */
        public string $type,
        /** One of `draft`, `active`, `inactive`. */
        public string $status,
        public bool $active,
        /** The settings that are not secrets. */
        public SsoConnectionConfig $config,
        /** null when the environment owns it: it signs people in and enrols them nowhere. */
        public ?string $organizationId = null,
        /** The catalogue provider a social sign-in connection came from; null for an organization's own identity provider. */
        public ?string $provider = null,
        /** false for a draft created with pending_idp whose identity-provider details are still missing. Activation refuses it. */
        public ?bool $complete = null,
        /** What to paste into the identity provider: this connection's own entity id, ACS URL and SP metadata URL (SAML), or its redirect URI (OIDC). null for a social sign-in connection. */
        public ?SsoConnectionServiceProvider $serviceProvider = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'SsoConnection', Value::string(...)),
            name: Field::required($data, 'name', 'SsoConnection', Value::string(...)),
            type: Field::required($data, 'type', 'SsoConnection', Value::string(...)),
            status: Field::required($data, 'status', 'SsoConnection', Value::string(...)),
            active: Field::required($data, 'active', 'SsoConnection', Value::bool(...)),
            config: Field::required($data, 'config', 'SsoConnection', Value::dto(SsoConnectionConfig::fromArray(...))),
            organizationId: Field::optional($data, 'organization_id', 'SsoConnection', Value::string(...)),
            provider: Field::optional($data, 'provider', 'SsoConnection', Value::string(...)),
            complete: Field::optional($data, 'complete', 'SsoConnection', Value::bool(...)),
            serviceProvider: Field::optional($data, 'service_provider', 'SsoConnection', Value::dto(SsoConnectionServiceProvider::fromArray(...))),
            createdAt: Field::optional($data, 'created_at', 'SsoConnection', Value::string(...)),
            updatedAt: Field::optional($data, 'updated_at', 'SsoConnection', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'type' => $this->type,
            'provider' => $this->provider,
            'status' => $this->status,
            'active' => $this->active,
            'complete' => $this->complete,
            'config' => $this->config->toArray(),
            'service_provider' => $this->serviceProvider?->toArray(),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
