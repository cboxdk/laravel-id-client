<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `SignalsSourceListItem` on the environment plane. */
readonly class SignalsSourceListItem implements JsonSerializable
{
    public function __construct(
        /** One of `2.0.0`. */
        public string $contractVersion,
        public string $id,
        public string $producerEnvironment,
        public SignalsSourceListItemPosition $position,
        /** One of `event`, `relation`. */
        public string $kind,
        public string $occurredAt,
        public string $recordedAt,
        public ?string $organizationId = null,
        /** One of `identity.authentication`, `identity.session`, `identity.token`, `identity.account`, `identity.activity`. */
        public ?string $name = null,
        /** @var array<string, mixed> */
        public ?array $data = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            contractVersion: Field::required($data, 'contract_version', 'SignalsSourceListItem', Value::string(...)),
            id: Field::required($data, 'id', 'SignalsSourceListItem', Value::string(...)),
            producerEnvironment: Field::required($data, 'producer_environment', 'SignalsSourceListItem', Value::string(...)),
            position: Field::required($data, 'position', 'SignalsSourceListItem', Value::dto(SignalsSourceListItemPosition::fromArray(...))),
            kind: Field::required($data, 'kind', 'SignalsSourceListItem', Value::string(...)),
            occurredAt: Field::required($data, 'occurred_at', 'SignalsSourceListItem', Value::string(...)),
            recordedAt: Field::required($data, 'recorded_at', 'SignalsSourceListItem', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'SignalsSourceListItem', Value::string(...)),
            name: Field::optional($data, 'name', 'SignalsSourceListItem', Value::string(...)),
            data: Field::optional($data, 'data', 'SignalsSourceListItem', Value::object(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract_version' => $this->contractVersion,
            'id' => $this->id,
            'producer_environment' => $this->producerEnvironment,
            'organization_id' => $this->organizationId,
            'position' => $this->position->toArray(),
            'kind' => $this->kind,
            'name' => $this->name,
            'occurred_at' => $this->occurredAt,
            'recorded_at' => $this->recordedAt,
            'data' => $this->data,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
