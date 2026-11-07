<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AuthenticatorMeListResult` on the environment plane. */
readonly class AuthenticatorMeListResult implements JsonSerializable
{
    public function __construct(
        public string $subject,
        public string $label,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            subject: Field::required($data, 'subject', 'AuthenticatorMeListResult', Value::string(...)),
            label: Field::required($data, 'label', 'AuthenticatorMeListResult', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'label' => $this->label,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
