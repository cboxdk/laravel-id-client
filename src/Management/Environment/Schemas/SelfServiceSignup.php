<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SelfServiceSignup` on the environment plane. */
readonly class SelfServiceSignup implements JsonSerializable
{
    public function __construct(
        public bool $enabled,
        /** False on a single-tenant install, where sign-up follows CBOX_ID_SIGNUP_MODE and the switch changes nothing. */
        public bool $decidedHere,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: Field::required($data, 'enabled', 'SelfServiceSignup', Value::bool(...)),
            decidedHere: Field::required($data, 'decided_here', 'SelfServiceSignup', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'decided_here' => $this->decidedHere,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
