<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * An environment, and — when the request asked for one — its first management key.
 *
 * `#/components/schemas/CreatedEnvironment` on the workspace plane.
 */
readonly class CreatedEnvironment implements JsonSerializable
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $slug = null,
        /** One of `production`, `sandbox`. */
        public ?string $type = null,
        public ?string $status = null,
        /** The project (billing anchor) this environment belongs to. */
        public ?string $projectId = null,
        public ?string $domain = null,
        /** The environment's own host — where its OIDC/environment API lives. */
        public ?string $issuer = null,
        public ?EnvironmentKey $initialKey = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::optional($data, 'id', 'CreatedEnvironment', Value::string(...)),
            name: Field::optional($data, 'name', 'CreatedEnvironment', Value::string(...)),
            slug: Field::optional($data, 'slug', 'CreatedEnvironment', Value::string(...)),
            type: Field::optional($data, 'type', 'CreatedEnvironment', Value::string(...)),
            status: Field::optional($data, 'status', 'CreatedEnvironment', Value::string(...)),
            projectId: Field::optional($data, 'project_id', 'CreatedEnvironment', Value::string(...)),
            domain: Field::optional($data, 'domain', 'CreatedEnvironment', Value::string(...)),
            issuer: Field::optional($data, 'issuer', 'CreatedEnvironment', Value::string(...)),
            initialKey: Field::optional($data, 'initial_key', 'CreatedEnvironment', Value::dto(EnvironmentKey::fromArray(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'status' => $this->status,
            'project_id' => $this->projectId,
            'domain' => $this->domain,
            'issuer' => $this->issuer,
            'initial_key' => $this->initialKey?->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
