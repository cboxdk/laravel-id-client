<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/OrganizationDomain` on the environment plane. */
readonly class OrganizationDomain implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $organizationId,
        /** An email domain the organization claims, like acme.com. */
        public string $domain,
        public bool $verified,
        /** Whether everyone signing in with an address here is routed to the organization's SSO. */
        public bool $capture,
        /** Where to publish the DNS TXT record that proves the claim. */
        public string $recordName,
        /** What to publish there. */
        public string $recordValue,
        public ?string $verifiedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'OrganizationDomain', Value::string(...)),
            organizationId: Field::required($data, 'organization_id', 'OrganizationDomain', Value::string(...)),
            domain: Field::required($data, 'domain', 'OrganizationDomain', Value::string(...)),
            verified: Field::required($data, 'verified', 'OrganizationDomain', Value::bool(...)),
            capture: Field::required($data, 'capture', 'OrganizationDomain', Value::bool(...)),
            recordName: Field::required($data, 'record_name', 'OrganizationDomain', Value::string(...)),
            recordValue: Field::required($data, 'record_value', 'OrganizationDomain', Value::string(...)),
            verifiedAt: Field::optional($data, 'verified_at', 'OrganizationDomain', Value::string(...)),
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
            'record_name' => $this->recordName,
            'record_value' => $this->recordValue,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
