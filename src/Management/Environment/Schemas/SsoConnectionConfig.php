<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * The settings that are not secrets.
 *
 * `SsoConnectionConfig` on the environment plane.
 */
readonly class SsoConnectionConfig implements JsonSerializable
{
    public function __construct(
        public ?string $idpEntityId = null,
        public ?string $idpSsoUrl = null,
        public ?string $spEntityId = null,
        public ?string $spAcsUrl = null,
        public ?string $issuer = null,
        public ?string $clientId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            idpEntityId: Field::optional($data, 'idp_entity_id', 'SsoConnectionConfig', Value::string(...)),
            idpSsoUrl: Field::optional($data, 'idp_sso_url', 'SsoConnectionConfig', Value::string(...)),
            spEntityId: Field::optional($data, 'sp_entity_id', 'SsoConnectionConfig', Value::string(...)),
            spAcsUrl: Field::optional($data, 'sp_acs_url', 'SsoConnectionConfig', Value::string(...)),
            issuer: Field::optional($data, 'issuer', 'SsoConnectionConfig', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'SsoConnectionConfig', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'idp_entity_id' => $this->idpEntityId,
            'idp_sso_url' => $this->idpSsoUrl,
            'sp_entity_id' => $this->spEntityId,
            'sp_acs_url' => $this->spAcsUrl,
            'issuer' => $this->issuer,
            'client_id' => $this->clientId,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
