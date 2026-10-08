<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `ProvisionedWorkspaceOwner` on the platform plane. */
readonly class ProvisionedWorkspaceOwner implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'ProvisionedWorkspaceOwner', Value::string(...)),
            email: Field::required($data, 'email', 'ProvisionedWorkspaceOwner', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
