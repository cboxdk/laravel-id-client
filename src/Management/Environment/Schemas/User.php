<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/User` on the environment plane. */
readonly class User implements JsonSerializable
{
    public function __construct(
        public ?string $id = null,
        public ?string $email = null,
        public ?string $name = null,
        /** One of `active`, `disabled`, `locked`. */
        public ?string $status = null,
        public ?string $emailVerifiedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::optional($data, 'id', 'User', Value::string(...)),
            email: Field::optional($data, 'email', 'User', Value::string(...)),
            name: Field::optional($data, 'name', 'User', Value::string(...)),
            status: Field::optional($data, 'status', 'User', Value::string(...)),
            emailVerifiedAt: Field::optional($data, 'email_verified_at', 'User', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'status' => $this->status,
            'email_verified_at' => $this->emailVerifiedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
