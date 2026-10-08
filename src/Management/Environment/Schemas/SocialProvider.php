<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * Its client secret and private key are write-only and never returned.
 *
 * `#/components/schemas/SocialProvider` on the environment plane.
 */
readonly class SocialProvider implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** One of `oidc`, `oauth2`. */
        public string $protocol,
        public string $status,
        /** The redirect URI to register with the provider. */
        public string $callbackUri,
        public ?string $organizationId = null,
        /** The catalogue key: google, github, apple… */
        public ?string $provider = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'SocialProvider', Value::string(...)),
            name: Field::required($data, 'name', 'SocialProvider', Value::string(...)),
            protocol: Field::required($data, 'protocol', 'SocialProvider', Value::string(...)),
            status: Field::required($data, 'status', 'SocialProvider', Value::string(...)),
            callbackUri: Field::required($data, 'callback_uri', 'SocialProvider', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'SocialProvider', Value::string(...)),
            provider: Field::optional($data, 'provider', 'SocialProvider', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'SocialProvider', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'provider' => $this->provider,
            'name' => $this->name,
            'protocol' => $this->protocol,
            'status' => $this->status,
            'callback_uri' => $this->callbackUri,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
