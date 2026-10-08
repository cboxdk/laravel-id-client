<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AccessReview` on the environment plane. */
readonly class AccessReview implements JsonSerializable
{
    public function __construct(
        public string $id,
        public bool $staff,
        public string $name,
        /** One of `open`, `closed`. */
        public string $status,
        public bool $open,
        /**
         * What closing does to an item nobody decided.
         * One of `revoke`, `certify`.
         */
        public string $pendingPolicy,
        public int $itemCount,
        /** null for a staff review of environment-wide grants. */
        public ?string $organizationId = null,
        public ?string $dueAt = null,
        public ?string $closedAt = null,
        /** The person or key that opened it. */
        public ?string $createdBy = null,
        public ?string $createdAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AccessReview', Value::string(...)),
            staff: Field::required($data, 'staff', 'AccessReview', Value::bool(...)),
            name: Field::required($data, 'name', 'AccessReview', Value::string(...)),
            status: Field::required($data, 'status', 'AccessReview', Value::string(...)),
            open: Field::required($data, 'open', 'AccessReview', Value::bool(...)),
            pendingPolicy: Field::required($data, 'pending_policy', 'AccessReview', Value::string(...)),
            itemCount: Field::required($data, 'item_count', 'AccessReview', Value::int(...)),
            organizationId: Field::optional($data, 'organization_id', 'AccessReview', Value::string(...)),
            dueAt: Field::optional($data, 'due_at', 'AccessReview', Value::string(...)),
            closedAt: Field::optional($data, 'closed_at', 'AccessReview', Value::string(...)),
            createdBy: Field::optional($data, 'created_by', 'AccessReview', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'AccessReview', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'staff' => $this->staff,
            'name' => $this->name,
            'status' => $this->status,
            'open' => $this->open,
            'pending_policy' => $this->pendingPolicy,
            'item_count' => $this->itemCount,
            'due_at' => $this->dueAt,
            'closed_at' => $this->closedAt,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
