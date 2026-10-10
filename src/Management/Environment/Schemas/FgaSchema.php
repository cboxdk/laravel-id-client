<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaSchema` on the environment plane. */
readonly class FgaSchema implements JsonSerializable
{
    public function __construct(
        /** false until a schema is first saved. */
        public bool $defined,
        /** How many times the schema has been replaced. */
        public int $version,
        /**
         * The parsed types, each with its relations and how each is decided.
         *
         * @var list<FgaType>
         */
        public array $types,
        /** The revision the model is at. */
        public string $consistencyToken,
        /** The source as it was written, comments and all. */
        public ?string $schema = null,
        public ?string $updatedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            defined: Field::required($data, 'defined', 'FgaSchema', Value::bool(...)),
            version: Field::required($data, 'version', 'FgaSchema', Value::int(...)),
            types: Field::required($data, 'types', 'FgaSchema', Value::list(Value::dto(FgaType::fromArray(...)))),
            consistencyToken: Field::required($data, 'consistency_token', 'FgaSchema', Value::string(...)),
            schema: Field::optional($data, 'schema', 'FgaSchema', Value::string(...)),
            updatedAt: Field::optional($data, 'updated_at', 'FgaSchema', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'defined' => $this->defined,
            'schema' => $this->schema,
            'version' => $this->version,
            'types' => array_map(static fn (FgaType $item) => $item->toArray(), $this->types),
            'updated_at' => $this->updatedAt,
            'consistency_token' => $this->consistencyToken,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
