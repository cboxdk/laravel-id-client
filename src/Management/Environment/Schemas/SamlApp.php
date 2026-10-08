<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * An application that trusts this environment as its SAML identity provider. Its certificate is write-only. An application owned by an organization is only ever asserted to that organization's active members; one with no organization is open to every person in the environment.
 *
 * `#/components/schemas/SamlApp` on the environment plane.
 */
readonly class SamlApp implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $entityId,
        public string $acsUrl,
        public string $nameIdFormat,
        public string $nameIdAttribute,
        /** @var list<SamlAppAttributeMappingsItem> */
        public array $attributeMappings,
        public bool $wantAuthnRequestsSigned,
        public bool $hasCertificate,
        public string $status,
        /** The organization whose active members alone may sign in to it; null for an environment-wide application. */
        public ?string $organizationId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'SamlApp', Value::string(...)),
            entityId: Field::required($data, 'entity_id', 'SamlApp', Value::string(...)),
            acsUrl: Field::required($data, 'acs_url', 'SamlApp', Value::string(...)),
            nameIdFormat: Field::required($data, 'name_id_format', 'SamlApp', Value::string(...)),
            nameIdAttribute: Field::required($data, 'name_id_attribute', 'SamlApp', Value::string(...)),
            attributeMappings: Field::required($data, 'attribute_mappings', 'SamlApp', Value::list(Value::dto(SamlAppAttributeMappingsItem::fromArray(...)))),
            wantAuthnRequestsSigned: Field::required($data, 'want_authn_requests_signed', 'SamlApp', Value::bool(...)),
            hasCertificate: Field::required($data, 'has_certificate', 'SamlApp', Value::bool(...)),
            status: Field::required($data, 'status', 'SamlApp', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'SamlApp', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'SamlApp', Value::string(...)),
            updatedAt: Field::optional($data, 'updated_at', 'SamlApp', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entity_id' => $this->entityId,
            'acs_url' => $this->acsUrl,
            'name_id_format' => $this->nameIdFormat,
            'name_id_attribute' => $this->nameIdAttribute,
            'attribute_mappings' => array_map(static fn (SamlAppAttributeMappingsItem $item) => $item->toArray(), $this->attributeMappings),
            'want_authn_requests_signed' => $this->wantAuthnRequestsSigned,
            'has_certificate' => $this->hasCertificate,
            'organization_id' => $this->organizationId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
