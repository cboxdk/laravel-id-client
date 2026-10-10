<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `FgaSchemaValidationErrorsItem` on the environment plane. */
readonly class FgaSchemaValidationErrorsItem implements JsonSerializable
{
    public function __construct(
        /** 0 when the problem is about the whole schema. */
        public int $line,
        public string $message,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            line: Field::required($data, 'line', 'FgaSchemaValidationErrorsItem', Value::int(...)),
            message: Field::required($data, 'message', 'FgaSchemaValidationErrorsItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'line' => $this->line,
            'message' => $this->message,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
