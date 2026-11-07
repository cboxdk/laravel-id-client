<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `SignalsSourceListItemPosition` on the environment plane. */
readonly class SignalsSourceListItemPosition implements JsonSerializable
{
    public function __construct(
        public string $sourceId,
        /** One of `0`. */
        public string $partition,
        public string $generation,
        public string $sequence,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            sourceId: Field::required($data, 'source_id', 'SignalsSourceListItemPosition', Value::string(...)),
            partition: Field::required($data, 'partition', 'SignalsSourceListItemPosition', Value::string(...)),
            generation: Field::required($data, 'generation', 'SignalsSourceListItemPosition', Value::string(...)),
            sequence: Field::required($data, 'sequence', 'SignalsSourceListItemPosition', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source_id' => $this->sourceId,
            'partition' => $this->partition,
            'generation' => $this->generation,
            'sequence' => $this->sequence,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
