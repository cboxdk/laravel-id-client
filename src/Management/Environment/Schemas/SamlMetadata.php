<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * An identity provider's settings read from its SAML metadata. Nothing is stored.
 *
 * `#/components/schemas/SamlMetadata` on the environment plane.
 */
readonly class SamlMetadata implements JsonSerializable
{
    public function __construct(
        public string $idpEntityId,
        public string $idpSsoUrl,
        /** The IdP's public signing certificate, from its own metadata. */
        public string $idpX509cert,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            idpEntityId: Field::required($data, 'idp_entity_id', 'SamlMetadata', Value::string(...)),
            idpSsoUrl: Field::required($data, 'idp_sso_url', 'SamlMetadata', Value::string(...)),
            idpX509cert: Field::required($data, 'idp_x509cert', 'SamlMetadata', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'idp_entity_id' => $this->idpEntityId,
            'idp_sso_url' => $this->idpSsoUrl,
            'idp_x509cert' => $this->idpX509cert,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
