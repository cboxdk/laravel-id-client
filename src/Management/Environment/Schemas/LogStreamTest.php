<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/LogStreamTest` on the environment plane. */
readonly class LogStreamTest implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** Whether the destination accepted the test entry. */
        public bool $delivered,
        public string $testedAt,
        /** The destination's refusal, scrubbed of the stream's secret. */
        public ?string $error = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'LogStreamTest', Value::string(...)),
            delivered: Field::required($data, 'delivered', 'LogStreamTest', Value::bool(...)),
            testedAt: Field::required($data, 'tested_at', 'LogStreamTest', Value::string(...)),
            error: Field::optional($data, 'error', 'LogStreamTest', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'delivered' => $this->delivered,
            'error' => $this->error,
            'tested_at' => $this->testedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
