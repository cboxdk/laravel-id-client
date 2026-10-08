<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/CustomDomain` on the environment plane. */
readonly class CustomDomain implements JsonSerializable
{
    public function __construct(
        /** The verified domain serving this environment. */
        public ?string $domain = null,
        public ?string $verifiedAt = null,
        /** A domain waiting for its DNS proof, and the TXT record to publish. */
        public ?CustomDomainPending $pending = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            domain: Field::optional($data, 'domain', 'CustomDomain', Value::string(...)),
            verifiedAt: Field::optional($data, 'verified_at', 'CustomDomain', Value::string(...)),
            pending: Field::optional($data, 'pending', 'CustomDomain', Value::dto(CustomDomainPending::fromArray(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'verified_at' => $this->verifiedAt,
            'pending' => $this->pending?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
