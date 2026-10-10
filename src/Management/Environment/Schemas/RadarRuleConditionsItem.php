<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `RadarRuleConditionsItem` on the environment plane. */
readonly class RadarRuleConditionsItem implements JsonSerializable
{
    public function __construct(
        public string $field,
        /** One of `eq`, `neq`, `in`, `not_in`, `gt`, `gte`, `lt`, `lte`, `contains`, `not_contains`, `starts_with`, `ends_with`, `in_cidr`, `not_in_cidr`. */
        public string $operator,
        /** Normalised: a string, a number, true/false, or a list for the list operators. */
        public mixed $value = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            field: Field::required($data, 'field', 'RadarRuleConditionsItem', Value::string(...)),
            operator: Field::required($data, 'operator', 'RadarRuleConditionsItem', Value::string(...)),
            value: Field::optional($data, 'value', 'RadarRuleConditionsItem', Value::mixed(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
