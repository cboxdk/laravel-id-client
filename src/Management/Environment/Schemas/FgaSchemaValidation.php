<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaSchemaValidation` on the environment plane. */
readonly class FgaSchemaValidation implements JsonSerializable
{
    public function __construct(
        public bool $valid,
        /** @var list<FgaSchemaValidationErrorsItem> */
        public array $errors,
        /** @var list<FgaType> */
        public array $types,
        /** The schema as the platform prints it, when valid. */
        public ?string $canonical = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            valid: Field::required($data, 'valid', 'FgaSchemaValidation', Value::bool(...)),
            errors: Field::required($data, 'errors', 'FgaSchemaValidation', Value::list(Value::dto(FgaSchemaValidationErrorsItem::fromArray(...)))),
            types: Field::required($data, 'types', 'FgaSchemaValidation', Value::list(Value::dto(FgaType::fromArray(...)))),
            canonical: Field::optional($data, 'canonical', 'FgaSchemaValidation', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(static fn (FgaSchemaValidationErrorsItem $item) => $item->toArray(), $this->errors),
            'types' => array_map(static fn (FgaType $item) => $item->toArray(), $this->types),
            'canonical' => $this->canonical,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
