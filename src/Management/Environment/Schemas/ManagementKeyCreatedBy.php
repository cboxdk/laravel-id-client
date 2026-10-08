<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `ManagementKeyCreatedBy` on the environment plane. */
readonly class ManagementKeyCreatedBy implements JsonSerializable
{
    public function __construct(
        public string $type,
        public ?string $id = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Field::required($data, 'type', 'ManagementKeyCreatedBy', Value::string(...)),
            id: Field::optional($data, 'id', 'ManagementKeyCreatedBy', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
