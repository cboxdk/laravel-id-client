<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AccessReviewItem` on the environment plane. */
readonly class AccessReviewItem implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $reviewId,
        public string $subjectId,
        /** One of `role`, `membership`, `environment_role`. */
        public string $accessType,
        /** The role id, or the organization id of a membership. */
        public string $accessRef,
        /** One of `pending`, `certified`, `revoked`. */
        public string $decision,
        /** Whether a revoke was carried out when the review closed. */
        public bool $applied,
        public ?string $organizationId = null,
        public ?string $decidedBy = null,
        public ?string $decidedAt = null,
        public ?string $note = null,
        /** Why a revoke could not be applied. */
        public ?string $applicationNote = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AccessReviewItem', Value::string(...)),
            reviewId: Field::required($data, 'review_id', 'AccessReviewItem', Value::string(...)),
            subjectId: Field::required($data, 'subject_id', 'AccessReviewItem', Value::string(...)),
            accessType: Field::required($data, 'access_type', 'AccessReviewItem', Value::string(...)),
            accessRef: Field::required($data, 'access_ref', 'AccessReviewItem', Value::string(...)),
            decision: Field::required($data, 'decision', 'AccessReviewItem', Value::string(...)),
            applied: Field::required($data, 'applied', 'AccessReviewItem', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'AccessReviewItem', Value::string(...)),
            decidedBy: Field::optional($data, 'decided_by', 'AccessReviewItem', Value::string(...)),
            decidedAt: Field::optional($data, 'decided_at', 'AccessReviewItem', Value::string(...)),
            note: Field::optional($data, 'note', 'AccessReviewItem', Value::string(...)),
            applicationNote: Field::optional($data, 'application_note', 'AccessReviewItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'review_id' => $this->reviewId,
            'subject_id' => $this->subjectId,
            'access_type' => $this->accessType,
            'access_ref' => $this->accessRef,
            'organization_id' => $this->organizationId,
            'decision' => $this->decision,
            'decided_by' => $this->decidedBy,
            'decided_at' => $this->decidedAt,
            'note' => $this->note,
            'applied' => $this->applied,
            'application_note' => $this->applicationNote,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
