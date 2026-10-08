<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AuditLogVerification` on the environment plane. */
readonly class AuditLogVerification implements JsonSerializable
{
    public function __construct(
        public string $organizationId,
        /** Every event checked is unchanged and in place. */
        public bool $valid,
        /** The check reached the chain's head. When false, continue from last_sequence + 1. */
        public bool $complete,
        public int $verifiedCount,
        public int $headSequence,
        public ?int $firstSequence = null,
        public ?int $lastSequence = null,
        public ?int $brokenAtSequence = null,
        /** One of `missing`, `link`, `hash`, `truncated`. */
        public ?string $reason = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            organizationId: Field::required($data, 'organization_id', 'AuditLogVerification', Value::string(...)),
            valid: Field::required($data, 'valid', 'AuditLogVerification', Value::bool(...)),
            complete: Field::required($data, 'complete', 'AuditLogVerification', Value::bool(...)),
            verifiedCount: Field::required($data, 'verified_count', 'AuditLogVerification', Value::int(...)),
            headSequence: Field::required($data, 'head_sequence', 'AuditLogVerification', Value::int(...)),
            firstSequence: Field::optional($data, 'first_sequence', 'AuditLogVerification', Value::int(...)),
            lastSequence: Field::optional($data, 'last_sequence', 'AuditLogVerification', Value::int(...)),
            brokenAtSequence: Field::optional($data, 'broken_at_sequence', 'AuditLogVerification', Value::int(...)),
            reason: Field::optional($data, 'reason', 'AuditLogVerification', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'valid' => $this->valid,
            'complete' => $this->complete,
            'verified_count' => $this->verifiedCount,
            'first_sequence' => $this->firstSequence,
            'last_sequence' => $this->lastSequence,
            'head_sequence' => $this->headSequence,
            'broken_at_sequence' => $this->brokenAtSequence,
            'reason' => $this->reason,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
