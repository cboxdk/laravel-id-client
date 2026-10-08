<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * An app's configuration without its identity or credentials — deterministic (lists
 * sorted), versioned, and refused rather than half-read when it carries a key this
 * server does not know.
 *
 * `#/components/schemas/AppBlueprint` on the environment plane.
 */
readonly class AppBlueprint implements JsonSerializable
{
    public function __construct(
        /** One of `cbox-id.client-blueprint`. */
        public string $kind,
        /** One of `1`. */
        public int $version,
        public string $name,
        /** One of `confidential`, `public`. */
        public string $clientType,
        public ?string $tokenEndpointAuthMethod = null,
        /** @var list<string> */
        public ?array $grantTypes = null,
        /** @var list<string> */
        public ?array $redirectUris = null,
        /** @var list<string> */
        public ?array $postLogoutRedirectUris = null,
        /** @var list<string> */
        public ?array $scopes = null,
        public ?bool $firstParty = null,
        public ?string $manifestUrl = null,
        public ?int $accessTokenTtl = null,
        public ?string $backchannelLogoutUri = null,
        public ?bool $backchannelLogoutSessionRequired = null,
        public ?string $apiKeyPrefix = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: Field::required($data, 'kind', 'AppBlueprint', Value::string(...)),
            version: Field::required($data, 'version', 'AppBlueprint', Value::int(...)),
            name: Field::required($data, 'name', 'AppBlueprint', Value::string(...)),
            clientType: Field::required($data, 'client_type', 'AppBlueprint', Value::string(...)),
            tokenEndpointAuthMethod: Field::optional($data, 'token_endpoint_auth_method', 'AppBlueprint', Value::string(...)),
            grantTypes: Field::optional($data, 'grant_types', 'AppBlueprint', Value::list(Value::string(...))),
            redirectUris: Field::optional($data, 'redirect_uris', 'AppBlueprint', Value::list(Value::string(...))),
            postLogoutRedirectUris: Field::optional($data, 'post_logout_redirect_uris', 'AppBlueprint', Value::list(Value::string(...))),
            scopes: Field::optional($data, 'scopes', 'AppBlueprint', Value::list(Value::string(...))),
            firstParty: Field::optional($data, 'first_party', 'AppBlueprint', Value::bool(...)),
            manifestUrl: Field::optional($data, 'manifest_url', 'AppBlueprint', Value::string(...)),
            accessTokenTtl: Field::optional($data, 'access_token_ttl', 'AppBlueprint', Value::int(...)),
            backchannelLogoutUri: Field::optional($data, 'backchannel_logout_uri', 'AppBlueprint', Value::string(...)),
            backchannelLogoutSessionRequired: Field::optional($data, 'backchannel_logout_session_required', 'AppBlueprint', Value::bool(...)),
            apiKeyPrefix: Field::optional($data, 'api_key_prefix', 'AppBlueprint', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'version' => $this->version,
            'name' => $this->name,
            'client_type' => $this->clientType,
            'token_endpoint_auth_method' => $this->tokenEndpointAuthMethod,
            'grant_types' => $this->grantTypes,
            'redirect_uris' => $this->redirectUris,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUris,
            'scopes' => $this->scopes,
            'first_party' => $this->firstParty,
            'manifest_url' => $this->manifestUrl,
            'access_token_ttl' => $this->accessTokenTtl,
            'backchannel_logout_uri' => $this->backchannelLogoutUri,
            'backchannel_logout_session_required' => $this->backchannelLogoutSessionRequired,
            'api_key_prefix' => $this->apiKeyPrefix,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
