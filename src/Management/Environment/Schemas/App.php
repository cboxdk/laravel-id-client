<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/App` on the environment plane. */
readonly class App implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $clientId,
        public string $name,
        /**
         * The kind, read back from its grants; `advanced` when they match no preset.
         * One of `web`, `spa`, `cli`, `service`, `agent`, `advanced`.
         */
        public string $type,
        /** One of `confidential`, `public`. */
        public string $clientType,
        public bool $firstParty,
        /** @var list<string> */
        public array $grantTypes,
        /** @var list<string> */
        public array $redirectUris,
        /** @var list<string> */
        public array $scopes,
        public ?string $organizationId = null,
        /** @var list<string> */
        public ?array $postLogoutRedirectUris = null,
        /** Where it publishes its roles-and-permissions manifest. */
        public ?string $manifestUrl = null,
        /** Its access-token lifetime in seconds; null for the install's default. */
        public ?int $accessTokenTtl = null,
        /** Where it is sent a logout token (OIDC Back-Channel Logout); null when it is not told. */
        public ?string $backchannelLogoutUri = null,
        public ?bool $backchannelLogoutSessionRequired = null,
        /** The prefix of its customer API keys; null when it accepts none. */
        public ?string $apiKeyPrefix = null,
        public ?string $createdAt = null,
        /** Only on the response that created (or copied) the app, and only for an app that authenticates with a secret. Never again — null on an idempotent replay. */
        public ?string $clientSecret = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'App', Value::string(...)),
            clientId: Field::required($data, 'client_id', 'App', Value::string(...)),
            name: Field::required($data, 'name', 'App', Value::string(...)),
            type: Field::required($data, 'type', 'App', Value::string(...)),
            clientType: Field::required($data, 'client_type', 'App', Value::string(...)),
            firstParty: Field::required($data, 'first_party', 'App', Value::bool(...)),
            grantTypes: Field::required($data, 'grant_types', 'App', Value::list(Value::string(...))),
            redirectUris: Field::required($data, 'redirect_uris', 'App', Value::list(Value::string(...))),
            scopes: Field::required($data, 'scopes', 'App', Value::list(Value::string(...))),
            organizationId: Field::optional($data, 'organization_id', 'App', Value::string(...)),
            postLogoutRedirectUris: Field::optional($data, 'post_logout_redirect_uris', 'App', Value::list(Value::string(...))),
            manifestUrl: Field::optional($data, 'manifest_url', 'App', Value::string(...)),
            accessTokenTtl: Field::optional($data, 'access_token_ttl', 'App', Value::int(...)),
            backchannelLogoutUri: Field::optional($data, 'backchannel_logout_uri', 'App', Value::string(...)),
            backchannelLogoutSessionRequired: Field::optional($data, 'backchannel_logout_session_required', 'App', Value::bool(...)),
            apiKeyPrefix: Field::optional($data, 'api_key_prefix', 'App', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'App', Value::string(...)),
            clientSecret: Field::optional($data, 'client_secret', 'App', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'name' => $this->name,
            'type' => $this->type,
            'client_type' => $this->clientType,
            'organization_id' => $this->organizationId,
            'first_party' => $this->firstParty,
            'grant_types' => $this->grantTypes,
            'redirect_uris' => $this->redirectUris,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUris,
            'scopes' => $this->scopes,
            'manifest_url' => $this->manifestUrl,
            'access_token_ttl' => $this->accessTokenTtl,
            'backchannel_logout_uri' => $this->backchannelLogoutUri,
            'backchannel_logout_session_required' => $this->backchannelLogoutSessionRequired,
            'api_key_prefix' => $this->apiKeyPrefix,
            'created_at' => $this->createdAt,
            'client_secret' => $this->clientSecret,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
