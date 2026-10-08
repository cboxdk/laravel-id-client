<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `SamlAppAttributeMappingsItem` on the environment plane. */
readonly class SamlAppAttributeMappingsItem implements JsonSerializable
{
    public function __construct(
        public string $attribute,
        public string $field,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            attribute: Field::required($data, 'attribute', 'SamlAppAttributeMappingsItem', Value::string(...)),
            field: Field::required($data, 'field', 'SamlAppAttributeMappingsItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'attribute' => $this->attribute,
            'field' => $this->field,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
