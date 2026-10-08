<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `SsoDomainVerification` on the environment plane. */
readonly class SsoDomainVerification implements JsonSerializable
{
    public function __construct(
        /** One of `TXT`. */
        public string $type,
        public string $name,
        public string $value,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Field::required($data, 'type', 'SsoDomainVerification', Value::string(...)),
            name: Field::required($data, 'name', 'SsoDomainVerification', Value::string(...)),
            value: Field::required($data, 'value', 'SsoDomainVerification', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'value' => $this->value,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
