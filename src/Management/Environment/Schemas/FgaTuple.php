<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaTuple` on the environment plane. */
readonly class FgaTuple implements JsonSerializable
{
    public function __construct(
        public string $resourceType,
        public string $resourceId,
        public string $relation,
        public FgaSubject $subject,
        /** The same tuple in the notation: `document:readme#viewer@group:eng#member`. */
        public string $tuple,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            resourceType: Field::required($data, 'resource_type', 'FgaTuple', Value::string(...)),
            resourceId: Field::required($data, 'resource_id', 'FgaTuple', Value::string(...)),
            relation: Field::required($data, 'relation', 'FgaTuple', Value::string(...)),
            subject: Field::required($data, 'subject', 'FgaTuple', Value::dto(FgaSubject::fromArray(...))),
            tuple: Field::required($data, 'tuple', 'FgaTuple', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'relation' => $this->relation,
            'subject' => $this->subject->toArray(),
            'tuple' => $this->tuple,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
