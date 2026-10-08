<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AppearanceTheme` on the environment plane. */
readonly class AppearanceTheme implements JsonSerializable
{
    public function __construct(
        public ?string $preset = null,
        public ?string $radius = null,
        public ?string $font = null,
        public ?ThemeMode $light = null,
        public ?ThemeMode $dark = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            preset: Field::optional($data, 'preset', 'AppearanceTheme', Value::string(...)),
            radius: Field::optional($data, 'radius', 'AppearanceTheme', Value::string(...)),
            font: Field::optional($data, 'font', 'AppearanceTheme', Value::string(...)),
            light: Field::optional($data, 'light', 'AppearanceTheme', Value::dto(ThemeMode::fromArray(...))),
            dark: Field::optional($data, 'dark', 'AppearanceTheme', Value::dto(ThemeMode::fromArray(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset,
            'radius' => $this->radius,
            'font' => $this->font,
            'light' => $this->light?->toArray(),
            'dark' => $this->dark?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
