<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * What to paste into the identity provider: this connection's own entity id and ACS URL (SAML), or its redirect URI (OIDC). null for a social sign-in connection.
 *
 * `SsoConnectionServiceProvider` on the environment plane.
 */
readonly class SsoConnectionServiceProvider implements JsonSerializable
{
    public function __construct(
        public ?string $spEntityId = null,
        public ?string $spAcsUrl = null,
        public ?string $redirectUri = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            spEntityId: Field::optional($data, 'sp_entity_id', 'SsoConnectionServiceProvider', Value::string(...)),
            spAcsUrl: Field::optional($data, 'sp_acs_url', 'SsoConnectionServiceProvider', Value::string(...)),
            redirectUri: Field::optional($data, 'redirect_uri', 'SsoConnectionServiceProvider', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'sp_entity_id' => $this->spEntityId,
            'sp_acs_url' => $this->spAcsUrl,
            'redirect_uri' => $this->redirectUri,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
