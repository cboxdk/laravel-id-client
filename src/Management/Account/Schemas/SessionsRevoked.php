<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SessionsRevoked` on the account plane. */
readonly class SessionsRevoked implements JsonSerializable
{
    public function __construct(
        /** How many sessions were signed out. */
        public int $revoked,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            revoked: Field::required($data, 'revoked', 'SessionsRevoked', Value::int(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'revoked' => $this->revoked,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
