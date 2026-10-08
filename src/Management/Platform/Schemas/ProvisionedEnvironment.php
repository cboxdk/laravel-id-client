<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/ProvisionedEnvironment` on the platform plane. */
readonly class ProvisionedEnvironment implements JsonSerializable
{
    public function __construct(
        public string $environmentId,
        public PlatformOrganization $organization,
        public ProvisionedEnvironmentAdmin $admin,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            environmentId: Field::required($data, 'environment_id', 'ProvisionedEnvironment', Value::string(...)),
            organization: Field::required($data, 'organization', 'ProvisionedEnvironment', Value::dto(PlatformOrganization::fromArray(...))),
            admin: Field::required($data, 'admin', 'ProvisionedEnvironment', Value::dto(ProvisionedEnvironmentAdmin::fromArray(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'environment_id' => $this->environmentId,
            'organization' => $this->organization->toArray(),
            'admin' => $this->admin->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
