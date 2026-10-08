<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Profile` on the account plane. */
readonly class Profile implements JsonSerializable
{
    public function __construct(
        public string $id,
        public ?string $name = null,
        public ?string $email = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Profile', Value::string(...)),
            name: Field::optional($data, 'name', 'Profile', Value::string(...)),
            email: Field::optional($data, 'email', 'Profile', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
