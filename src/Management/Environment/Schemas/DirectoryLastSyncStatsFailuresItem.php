<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `DirectoryLastSyncStatsFailuresItem` on the environment plane. */
readonly class DirectoryLastSyncStatsFailuresItem implements JsonSerializable
{
    public function __construct(
        public ?string $externalId = null,
        public ?string $reason = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            externalId: Field::optional($data, 'external_id', 'DirectoryLastSyncStatsFailuresItem', Value::string(...)),
            reason: Field::optional($data, 'reason', 'DirectoryLastSyncStatsFailuresItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'reason' => $this->reason,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
