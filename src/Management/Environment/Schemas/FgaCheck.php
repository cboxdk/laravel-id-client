<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaCheck` on the environment plane. */
readonly class FgaCheck implements JsonSerializable
{
    public function __construct(
        public bool $allowed,
        public string $resourceType,
        public string $resourceId,
        public string $relation,
        public FgaSubject $subject,
        /** The revision the answer was decided at. */
        public string $consistencyToken,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            allowed: Field::required($data, 'allowed', 'FgaCheck', Value::bool(...)),
            resourceType: Field::required($data, 'resource_type', 'FgaCheck', Value::string(...)),
            resourceId: Field::required($data, 'resource_id', 'FgaCheck', Value::string(...)),
            relation: Field::required($data, 'relation', 'FgaCheck', Value::string(...)),
            subject: Field::required($data, 'subject', 'FgaCheck', Value::dto(FgaSubject::fromArray(...))),
            consistencyToken: Field::required($data, 'consistency_token', 'FgaCheck', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'relation' => $this->relation,
            'subject' => $this->subject->toArray(),
            'consistency_token' => $this->consistencyToken,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
