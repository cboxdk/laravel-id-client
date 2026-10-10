<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * The last pull's counts, and up to 50 records it could not reconcile — by the provider's own id, never by name or email.
 *
 * `DirectoryLastSyncStats` on the environment plane.
 */
readonly class DirectoryLastSyncStats implements JsonSerializable
{
    public function __construct(
        /** One of `full`, `incremental`. */
        public ?string $mode = null,
        public ?int $provisioned = null,
        public ?int $deprovisioned = null,
        public ?int $groups = null,
        /** Leavers and people who have not started, who never had an account. */
        public ?int $skipped = null,
        public ?int $failed = null,
        /** @var list<DirectoryLastSyncStatsFailuresItem> */
        public ?array $failures = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            mode: Field::optional($data, 'mode', 'DirectoryLastSyncStats', Value::string(...)),
            provisioned: Field::optional($data, 'provisioned', 'DirectoryLastSyncStats', Value::int(...)),
            deprovisioned: Field::optional($data, 'deprovisioned', 'DirectoryLastSyncStats', Value::int(...)),
            groups: Field::optional($data, 'groups', 'DirectoryLastSyncStats', Value::int(...)),
            skipped: Field::optional($data, 'skipped', 'DirectoryLastSyncStats', Value::int(...)),
            failed: Field::optional($data, 'failed', 'DirectoryLastSyncStats', Value::int(...)),
            failures: Field::optional($data, 'failures', 'DirectoryLastSyncStats', Value::list(Value::dto(DirectoryLastSyncStatsFailuresItem::fromArray(...)))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'provisioned' => $this->provisioned,
            'deprovisioned' => $this->deprovisioned,
            'groups' => $this->groups,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'failures' => $this->failures === null ? null : array_map(static fn (DirectoryLastSyncStatsFailuresItem $item) => $item->toArray(), $this->failures),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
