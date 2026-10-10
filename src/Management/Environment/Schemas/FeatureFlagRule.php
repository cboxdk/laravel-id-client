<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FeatureFlagRule` on the environment plane. */
readonly class FeatureFlagRule implements JsonSerializable
{
    public function __construct(
        /** The user's or organization's id. */
        public string $id,
        /** On or off for them. */
        public bool $enabled,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'FeatureFlagRule', Value::string(...)),
            enabled: Field::required($data, 'enabled', 'FeatureFlagRule', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
