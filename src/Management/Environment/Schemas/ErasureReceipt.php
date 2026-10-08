<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * The record of one erasure (GDPR Art. 17): what each store did, in numbers. Carries no personal data — safe to log, file with your Art. 30 records, or hand to the person who asked.
 *
 * `#/components/schemas/ErasureReceipt` on the environment plane.
 */
readonly class ErasureReceipt implements JsonSerializable
{
    public function __construct(
        /** The erased person's id. Kept: the row is pseudonymised in place, not deleted. */
        public string $subjectId,
        public string $erasedAt,
        /** Whether the account row's email and name were replaced with placeholders. */
        public bool $subjectPseudonymised,
        /**
         * One entry per store, in the order they ran — including stores that held nothing, so a receipt shows a store was checked.
         *
         * @var list<ErasureReceiptStepsItem>
         */
        public array $steps,
        /** The `user.erased` tombstone on the audit trail. */
        public ?string $auditEntryId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            subjectId: Field::required($data, 'subject_id', 'ErasureReceipt', Value::string(...)),
            erasedAt: Field::required($data, 'erased_at', 'ErasureReceipt', Value::string(...)),
            subjectPseudonymised: Field::required($data, 'subject_pseudonymised', 'ErasureReceipt', Value::bool(...)),
            steps: Field::required($data, 'steps', 'ErasureReceipt', Value::list(Value::dto(ErasureReceiptStepsItem::fromArray(...)))),
            auditEntryId: Field::optional($data, 'audit_entry_id', 'ErasureReceipt', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'subject_id' => $this->subjectId,
            'erased_at' => $this->erasedAt,
            'subject_pseudonymised' => $this->subjectPseudonymised,
            'audit_entry_id' => $this->auditEntryId,
            'steps' => array_map(static fn (ErasureReceiptStepsItem $item) => $item->toArray(), $this->steps),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
