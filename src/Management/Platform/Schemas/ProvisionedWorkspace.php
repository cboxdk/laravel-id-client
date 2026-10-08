<?php

declare(strict_types=1);

// GENERATED from openapi/platform.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Platform\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/ProvisionedWorkspace` on the platform plane. */
readonly class ProvisionedWorkspace implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ProvisionedWorkspaceOwner $owner,
        /** The workspace's first project. */
        public string $projectId,
        /** That project's first environment. */
        public string $environmentId,
        /** Whether the owner was emailed a link to set their password. */
        public bool $ownerInvited,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'ProvisionedWorkspace', Value::string(...)),
            name: Field::required($data, 'name', 'ProvisionedWorkspace', Value::string(...)),
            owner: Field::required($data, 'owner', 'ProvisionedWorkspace', Value::dto(ProvisionedWorkspaceOwner::fromArray(...))),
            projectId: Field::required($data, 'project_id', 'ProvisionedWorkspace', Value::string(...)),
            environmentId: Field::required($data, 'environment_id', 'ProvisionedWorkspace', Value::string(...)),
            ownerInvited: Field::required($data, 'owner_invited', 'ProvisionedWorkspace', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner' => $this->owner->toArray(),
            'project_id' => $this->projectId,
            'environment_id' => $this->environmentId,
            'owner_invited' => $this->ownerInvited,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
