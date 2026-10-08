<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `ErasureReceiptStepsItem` on the environment plane. */
readonly class ErasureReceiptStepsItem implements JsonSerializable
{
    public function __construct(
        /** The store, e.g. `identity.credentials`, `oauth.grants`, `devices.devices`. */
        public string $step,
        /**
         * What was removed or rewritten there, by item.
         *
         * @var array<string, mixed>
         */
        public array $counts,
        /** Why a step could not act, when it could not. */
        public ?string $note = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            step: Field::required($data, 'step', 'ErasureReceiptStepsItem', Value::string(...)),
            counts: Field::required($data, 'counts', 'ErasureReceiptStepsItem', Value::object(...)),
            note: Field::optional($data, 'note', 'ErasureReceiptStepsItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'counts' => $this->counts,
            'note' => $this->note,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
