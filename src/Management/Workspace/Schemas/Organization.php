<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/Organization` on the workspace plane. */
readonly class Organization implements JsonSerializable
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        /** One of `active`, `suspended`. */
        public ?string $status = null,
        /**
         * The workspace's projects with each one's plan/allowance. Present only for billing-readers (plans are per project).
         *
         * @var list<Project>
         */
        public ?array $projects = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::optional($data, 'id', 'Organization', Value::string(...)),
            name: Field::optional($data, 'name', 'Organization', Value::string(...)),
            status: Field::optional($data, 'status', 'Organization', Value::string(...)),
            projects: Field::optional($data, 'projects', 'Organization', Value::list(Value::dto(Project::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'projects' => $this->projects === null ? null : array_map(static fn (Project $item) => $item->toArray(), $this->projects),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
