<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A pipe: this environment's OAuth app at a third-party provider, through which people connect their own accounts. The client secret is write-only.
 *
 * `#/components/schemas/Pipe` on the environment plane.
 */
readonly class Pipe implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** One of `github`, `google`, `microsoft`, `slack`, `salesforce`, `hubspot`, `linear`, `notion`. */
        public string $provider,
        public string $name,
        /** Your app's OAuth client id at the provider. */
        public string $clientId,
        /**
         * What people are asked for when they connect.
         *
         * @var list<string>
         */
        public array $scopes,
        public bool $enabled,
        /** The callback URL to register at the provider. */
        public string $redirectUri,
        /**
         * The OAuth client ids of the apps that may lease its tokens.
         *
         * @var list<string>
         */
        public array $grants,
        /** How many people have connected an account through it. */
        public int $connections,
        /**
         * Per-installation values: Microsoft's `tenant`, Salesforce's `domain`.
         *
         * @var array<string, mixed>
         */
        public ?array $parameters = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Pipe', Value::string(...)),
            provider: Field::required($data, 'provider', 'Pipe', Value::string(...)),
            name: Field::required($data, 'name', 'Pipe', Value::string(...)),
            clientId: Field::required($data, 'client_id', 'Pipe', Value::string(...)),
            scopes: Field::required($data, 'scopes', 'Pipe', Value::list(Value::string(...))),
            enabled: Field::required($data, 'enabled', 'Pipe', Value::bool(...)),
            redirectUri: Field::required($data, 'redirect_uri', 'Pipe', Value::string(...)),
            grants: Field::required($data, 'grants', 'Pipe', Value::list(Value::string(...))),
            connections: Field::required($data, 'connections', 'Pipe', Value::int(...)),
            parameters: Field::optional($data, 'parameters', 'Pipe', Value::object(...)),
            createdAt: Field::optional($data, 'created_at', 'Pipe', Value::string(...)),
            updatedAt: Field::optional($data, 'updated_at', 'Pipe', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'client_id' => $this->clientId,
            'scopes' => $this->scopes,
            'parameters' => $this->parameters,
            'enabled' => $this->enabled,
            'redirect_uri' => $this->redirectUri,
            'grants' => $this->grants,
            'connections' => $this->connections,
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
