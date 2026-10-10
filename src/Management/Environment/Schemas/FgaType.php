<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaType` on the environment plane. */
readonly class FgaType implements JsonSerializable
{
    public function __construct(
        public string $name,
        /** @var list<FgaTypeRelationsItem> */
        public array $relations,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Field::required($data, 'name', 'FgaType', Value::string(...)),
            relations: Field::required($data, 'relations', 'FgaType', Value::list(Value::dto(FgaTypeRelationsItem::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'relations' => array_map(static fn (FgaTypeRelationsItem $item) => $item->toArray(), $this->relations),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
