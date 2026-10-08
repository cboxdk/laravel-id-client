<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Project` on the workspace plane. */
readonly class Project implements JsonSerializable
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $slug = null,
        /** One of `active`, `suspended`. */
        public ?string $status = null,
        /** This project's plan environment allowance. */
        public ?int $environmentLimit = null,
        public ?int $environmentsUsed = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::optional($data, 'id', 'Project', Value::string(...)),
            name: Field::optional($data, 'name', 'Project', Value::string(...)),
            slug: Field::optional($data, 'slug', 'Project', Value::string(...)),
            status: Field::optional($data, 'status', 'Project', Value::string(...)),
            environmentLimit: Field::optional($data, 'environment_limit', 'Project', Value::int(...)),
            environmentsUsed: Field::optional($data, 'environments_used', 'Project', Value::int(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'environment_limit' => $this->environmentLimit,
            'environments_used' => $this->environmentsUsed,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
