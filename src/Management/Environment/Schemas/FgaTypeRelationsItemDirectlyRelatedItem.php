<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `FgaTypeRelationsItemDirectlyRelatedItem` on the environment plane. */
readonly class FgaTypeRelationsItemDirectlyRelatedItem implements JsonSerializable
{
    public function __construct(
        public string $type,
        public ?string $relation = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Field::required($data, 'type', 'FgaTypeRelationsItemDirectlyRelatedItem', Value::string(...)),
            relation: Field::optional($data, 'relation', 'FgaTypeRelationsItemDirectlyRelatedItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'relation' => $this->relation,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
