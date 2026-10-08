<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Permission` on the environment plane. */
readonly class Permission implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** The `feature:action` key an app reads out of a token. */
        public string $name,
        /** Whether organizations may compose it into their own roles. */
        public bool $tenantAssignable,
        /** Authored here rather than declared by an app. */
        public bool $manual,
        /** Its app stopped declaring it. */
        public bool $orphaned,
        public ?string $description = null,
        /** The app that declared it; null for one authored here. */
        public ?string $clientId = null,
        /** Set for one organization's own; null for the shared tier. */
        public ?string $organizationId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'Permission', Value::string(...)),
            name: Field::required($data, 'name', 'Permission', Value::string(...)),
            tenantAssignable: Field::required($data, 'tenant_assignable', 'Permission', Value::bool(...)),
            manual: Field::required($data, 'manual', 'Permission', Value::bool(...)),
            orphaned: Field::required($data, 'orphaned', 'Permission', Value::bool(...)),
            description: Field::optional($data, 'description', 'Permission', Value::string(...)),
            clientId: Field::optional($data, 'client_id', 'Permission', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'Permission', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'client_id' => $this->clientId,
            'organization_id' => $this->organizationId,
            'tenant_assignable' => $this->tenantAssignable,
            'manual' => $this->manual,
            'orphaned' => $this->orphaned,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
