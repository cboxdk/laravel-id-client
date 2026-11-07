<?php

declare(strict_types=1);

// GENERATED from openapi/account.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Account\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `AppsListItem` on the account plane. */
readonly class AppsListItem implements JsonSerializable
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
            key: Field::required($data, 'key', 'AppsListItem', Value::string(...)),
            name: Field::required($data, 'name', 'AppsListItem', Value::string(...)),
            launchUrl: Field::required($data, 'launch_url', 'AppsListItem', Value::string(...)),
            position: Field::required($data, 'position', 'AppsListItem', Value::int(...)),
            version: Field::required($data, 'version', 'AppsListItem', Value::int(...)),
            description: Field::optional($data, 'description', 'AppsListItem', Value::string(...)),
            iconUrl: Field::optional($data, 'icon_url', 'AppsListItem', Value::string(...)),
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
