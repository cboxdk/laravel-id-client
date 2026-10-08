<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AuditLogExport` on the environment plane. */
readonly class AuditLogExport implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** One of `pending`, `ready`, `failed`, `expired`. */
        public string $state,
        /**
         * The filters it was asked with.
         *
         * @var array<string, mixed>
         */
        public array $filters,
        /** null for an export across the whole environment. */
        public ?string $organizationId = null,
        public ?int $rowCount = null,
        /**
         * Where the CSV downloads from once `ready` — signed, and valid for a few minutes;
         * read the export again for a fresh one. null otherwise, and on an idempotent replay.
         */
        public ?string $url = null,
        public ?string $createdAt = null,
        public ?string $completedAt = null,
        /** When the file is deleted. */
        public ?string $expiresAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AuditLogExport', Value::string(...)),
            state: Field::required($data, 'state', 'AuditLogExport', Value::string(...)),
            filters: Field::required($data, 'filters', 'AuditLogExport', Value::object(...)),
            organizationId: Field::optional($data, 'organization_id', 'AuditLogExport', Value::string(...)),
            rowCount: Field::optional($data, 'row_count', 'AuditLogExport', Value::int(...)),
            url: Field::optional($data, 'url', 'AuditLogExport', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'AuditLogExport', Value::string(...)),
            completedAt: Field::optional($data, 'completed_at', 'AuditLogExport', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'AuditLogExport', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'state' => $this->state,
            'filters' => $this->filters,
            'row_count' => $this->rowCount,
            'url' => $this->url,
            'created_at' => $this->createdAt,
            'completed_at' => $this->completedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
