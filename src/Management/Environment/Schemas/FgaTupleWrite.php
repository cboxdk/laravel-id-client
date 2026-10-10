<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaTupleWrite` on the environment plane. */
readonly class FgaTupleWrite implements JsonSerializable
{
    public function __construct(
        /** Tuples that were new. Re-writing an existing one is not counted. */
        public int $written,
        /** Tuples that were there to delete. */
        public int $deleted,
        /** Pass to a check that must see this write. */
        public string $consistencyToken,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            written: Field::required($data, 'written', 'FgaTupleWrite', Value::int(...)),
            deleted: Field::required($data, 'deleted', 'FgaTupleWrite', Value::int(...)),
            consistencyToken: Field::required($data, 'consistency_token', 'FgaTupleWrite', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'written' => $this->written,
            'deleted' => $this->deleted,
            'consistency_token' => $this->consistencyToken,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
