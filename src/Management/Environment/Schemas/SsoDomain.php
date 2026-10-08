<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SsoDomain` on the environment plane. */
readonly class SsoDomain implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $domain,
        public bool $verified,
        /** Everyone with an address at the domain must sign in through the organization's SSO. */
        public bool $capture,
        public ?string $verifiedAt = null,
        /** The DNS TXT record that proves the domain, while it is unverified. */
        public ?SsoDomainVerification $verification = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'SsoDomain', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'SsoDomain', Value::string(...)),
            domain: Field::required($data, 'domain', 'SsoDomain', Value::string(...)),
            verified: Field::required($data, 'verified', 'SsoDomain', Value::bool(...)),
            capture: Field::required($data, 'capture', 'SsoDomain', Value::bool(...)),
            verifiedAt: Field::optional($data, 'verified_at', 'SsoDomain', Value::string(...)),
            verification: Field::optional($data, 'verification', 'SsoDomain', Value::dto(SsoDomainVerification::fromArray(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'domain' => $this->domain,
            'verified' => $this->verified,
            'verified_at' => $this->verifiedAt,
            'capture' => $this->capture,
            'verification' => $this->verification?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
