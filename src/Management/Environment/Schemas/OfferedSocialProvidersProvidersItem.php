<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `OfferedSocialProvidersProvidersItem` on the environment plane. */
readonly class OfferedSocialProvidersProvidersItem implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /**
         * `environment` when inherited; `organization` when the organization's own stands in its place.
         * One of `environment`, `organization`.
         */
        public string $source,
        public string $callbackUri,
        public ?string $provider = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'OfferedSocialProvidersProvidersItem', Value::string(...)),
            name: Field::required($data, 'name', 'OfferedSocialProvidersProvidersItem', Value::string(...)),
            source: Field::required($data, 'source', 'OfferedSocialProvidersProvidersItem', Value::string(...)),
            callbackUri: Field::required($data, 'callback_uri', 'OfferedSocialProvidersProvidersItem', Value::string(...)),
            provider: Field::optional($data, 'provider', 'OfferedSocialProvidersProvidersItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->name,
            'source' => $this->source,
            'callback_uri' => $this->callbackUri,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
