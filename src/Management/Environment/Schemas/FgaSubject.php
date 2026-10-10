<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaSubject` on the environment plane. */
readonly class FgaSubject implements JsonSerializable
{
    public function __construct(
        /** The subject's type: `user`, or `group` for a userset. */
        public string $type,
        public string $id,
        /** For a userset — everybody with this relation on the subject (`member` of `group:eng`). null for one subject. */
        public ?string $relation = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            type: Field::required($data, 'type', 'FgaSubject', Value::string(...)),
            id: Field::required($data, 'id', 'FgaSubject', Value::string(...)),
            relation: Field::optional($data, 'relation', 'FgaSubject', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'relation' => $this->relation,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
