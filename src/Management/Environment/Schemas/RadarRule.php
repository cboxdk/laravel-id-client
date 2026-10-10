<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/RadarRule` on the environment plane. */
readonly class RadarRule implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        /** 1 is evaluated first; the first rule whose conditions all hold decides. */
        public int $position,
        public bool $enabled,
        /** One of `all`, `sign_in`, `sign_up`. */
        public string $appliesTo,
        /** One of `allow`, `challenge`, `block`. */
        public string $action,
        /** @var list<RadarRuleConditionsItem> */
        public array $conditions,
        /** The conditions as a person reads them. */
        public string $summary,
        public string $createdAt,
        public string $updatedAt,
        public ?string $description = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'RadarRule', Value::string(...)),
            name: Field::required($data, 'name', 'RadarRule', Value::string(...)),
            position: Field::required($data, 'position', 'RadarRule', Value::int(...)),
            enabled: Field::required($data, 'enabled', 'RadarRule', Value::bool(...)),
            appliesTo: Field::required($data, 'applies_to', 'RadarRule', Value::string(...)),
            action: Field::required($data, 'action', 'RadarRule', Value::string(...)),
            conditions: Field::required($data, 'conditions', 'RadarRule', Value::list(Value::dto(RadarRuleConditionsItem::fromArray(...)))),
            summary: Field::required($data, 'summary', 'RadarRule', Value::string(...)),
            createdAt: Field::required($data, 'created_at', 'RadarRule', Value::string(...)),
            updatedAt: Field::required($data, 'updated_at', 'RadarRule', Value::string(...)),
            description: Field::optional($data, 'description', 'RadarRule', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'position' => $this->position,
            'enabled' => $this->enabled,
            'applies_to' => $this->appliesTo,
            'action' => $this->action,
            'conditions' => array_map(static fn (RadarRuleConditionsItem $item) => $item->toArray(), $this->conditions),
            'summary' => $this->summary,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
