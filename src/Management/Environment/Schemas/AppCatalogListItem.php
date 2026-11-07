<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AppCatalogListItem` on the environment plane. */
readonly class AppCatalogListItem implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $name,
        public string $launchUrl,
        public int $position,
        public int $version,
        public ?string $description = null,
        public ?string $iconUrl = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Field::required($data, 'key', 'AppCatalogListItem', Value::string(...)),
            name: Field::required($data, 'name', 'AppCatalogListItem', Value::string(...)),
            launchUrl: Field::required($data, 'launch_url', 'AppCatalogListItem', Value::string(...)),
            position: Field::required($data, 'position', 'AppCatalogListItem', Value::int(...)),
            version: Field::required($data, 'version', 'AppCatalogListItem', Value::int(...)),
            description: Field::optional($data, 'description', 'AppCatalogListItem', Value::string(...)),
            iconUrl: Field::optional($data, 'icon_url', 'AppCatalogListItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'launch_url' => $this->launchUrl,
            'icon_url' => $this->iconUrl,
            'position' => $this->position,
            'version' => $this->version,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
