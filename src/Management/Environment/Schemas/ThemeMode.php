<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/ThemeMode` on the environment plane. */
readonly class ThemeMode implements JsonSerializable
{
    public function __construct(
        public ?string $primary = null,
        public ?string $background = null,
        public ?string $foreground = null,
        public ?string $muted = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            primary: Field::optional($data, 'primary', 'ThemeMode', Value::string(...)),
            background: Field::optional($data, 'background', 'ThemeMode', Value::string(...)),
            foreground: Field::optional($data, 'foreground', 'ThemeMode', Value::string(...)),
            muted: Field::optional($data, 'muted', 'ThemeMode', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'background' => $this->background,
            'foreground' => $this->foreground,
            'muted' => $this->muted,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
