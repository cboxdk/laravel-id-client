<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/DirectoryGroup` on the environment plane. */
readonly class DirectoryGroup implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $directoryId,
        public string $name,
        /**
         * The roles everyone in the group holds.
         *
         * @var list<string>
         */
        public array $roleIds,
        public ?string $externalId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'DirectoryGroup', Value::string(...)),
            directoryId: Field::required($data, 'directory_id', 'DirectoryGroup', Value::string(...)),
            name: Field::required($data, 'name', 'DirectoryGroup', Value::string(...)),
            roleIds: Field::required($data, 'role_ids', 'DirectoryGroup', Value::list(Value::string(...))),
            externalId: Field::optional($data, 'external_id', 'DirectoryGroup', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'directory_id' => $this->directoryId,
            'name' => $this->name,
            'external_id' => $this->externalId,
            'role_ids' => $this->roleIds,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
