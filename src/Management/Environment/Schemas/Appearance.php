<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Appearance` on the environment plane. */
readonly class Appearance implements JsonSerializable
{
    public function __construct(
        /** Whether this level has a theme of its own. */
        public bool $customized,
        public AppearanceTheme $theme,
        /** A remote logo URL saved before logos became uploads is still stored at this level. It is never fetched and no longer shown on any hosted page; upload a logo (or send `logo: null`) to clear it. */
        public bool $remoteLogoIgnored,
        /** null for the environment default. */
        public ?string $organizationId = null,
        /** The uploaded logo, served by this application at /brand-assets/…; null when none is uploaded at this level. Never a remote URL. */
        public ?string $logo = null,
        /** The uploaded favicon, served the same way; null when none. */
        public ?string $favicon = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            customized: Field::required($data, 'customized', 'Appearance', Value::bool(...)),
            theme: Field::required($data, 'theme', 'Appearance', Value::dto(AppearanceTheme::fromArray(...))),
            remoteLogoIgnored: Field::required($data, 'remote_logo_ignored', 'Appearance', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'Appearance', Value::string(...)),
            logo: Field::optional($data, 'logo', 'Appearance', Value::string(...)),
            favicon: Field::optional($data, 'favicon', 'Appearance', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'customized' => $this->customized,
            'theme' => $this->theme->toArray(),
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'remote_logo_ignored' => $this->remoteLogoIgnored,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
