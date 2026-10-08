<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `ActionApprovalsGetResult` on the workspace plane. */
readonly class ActionApprovalsGetResult implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** One of `pending`, `approved`, `denied`, `expired`, `consumed`. */
        public string $status,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'ActionApprovalsGetResult', Value::string(...)),
            status: Field::required($data, 'status', 'ActionApprovalsGetResult', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
