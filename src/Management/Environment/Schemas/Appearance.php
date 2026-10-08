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
        /** null for the environment default. */
        public ?string $organizationId = null,
        public ?string $logo = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            customized: Field::required($data, 'customized', 'Appearance', Value::bool(...)),
            theme: Field::required($data, 'theme', 'Appearance', Value::dto(AppearanceTheme::fromArray(...))),
            organizationId: Field::optional($data, 'organization_id', 'Appearance', Value::string(...)),
            logo: Field::optional($data, 'logo', 'Appearance', Value::string(...)),
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
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
