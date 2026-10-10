<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/RadarListEntry` on the environment plane. */
readonly class RadarListEntry implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** One of `allow`, `deny`. */
        public string $list,
        /** One of `ip`, `email`, `email_domain`, `device`. */
        public string $kind,
        /** Normalised: an IP or CIDR range, a lower-case address or domain, or a device id. */
        public string $value,
        public bool $active,
        public string $createdAt,
        public ?string $note = null,
        public ?string $expiresAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'RadarListEntry', Value::string(...)),
            list: Field::required($data, 'list', 'RadarListEntry', Value::string(...)),
            kind: Field::required($data, 'kind', 'RadarListEntry', Value::string(...)),
            value: Field::required($data, 'value', 'RadarListEntry', Value::string(...)),
            active: Field::required($data, 'active', 'RadarListEntry', Value::bool(...)),
            createdAt: Field::required($data, 'created_at', 'RadarListEntry', Value::string(...)),
            note: Field::optional($data, 'note', 'RadarListEntry', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'RadarListEntry', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'list' => $this->list,
            'kind' => $this->kind,
            'value' => $this->value,
            'note' => $this->note,
            'expires_at' => $this->expiresAt,
            'active' => $this->active,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
