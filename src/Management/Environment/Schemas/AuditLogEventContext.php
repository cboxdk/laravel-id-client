<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AuditLogEventContext` on the environment plane. */
readonly class AuditLogEventContext implements JsonSerializable
{
    public function __construct(
        public ?string $location = null,
        public ?string $userAgent = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            location: Field::optional($data, 'location', 'AuditLogEventContext', Value::string(...)),
            userAgent: Field::optional($data, 'user_agent', 'AuditLogEventContext', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'location' => $this->location,
            'user_agent' => $this->userAgent,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
