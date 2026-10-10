<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `RadarDecisionTriggeredItem` on the environment plane. */
readonly class RadarDecisionTriggeredItem implements JsonSerializable
{
    public function __construct(
        public string $rule,
        public string $name,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            rule: Field::required($data, 'rule', 'RadarDecisionTriggeredItem', Value::string(...)),
            name: Field::required($data, 'name', 'RadarDecisionTriggeredItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'name' => $this->name,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
