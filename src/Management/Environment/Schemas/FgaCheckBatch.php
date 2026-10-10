<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/FgaCheckBatch` on the environment plane. */
readonly class FgaCheckBatch implements JsonSerializable
{
    public function __construct(
        /**
         * One answer per check, in the order asked.
         *
         * @var list<FgaCheck>
         */
        public array $results,
        public ?string $consistencyToken = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            results: Field::required($data, 'results', 'FgaCheckBatch', Value::list(Value::dto(FgaCheck::fromArray(...)))),
            consistencyToken: Field::optional($data, 'consistency_token', 'FgaCheckBatch', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'results' => array_map(static fn (FgaCheck $item) => $item->toArray(), $this->results),
            'consistency_token' => $this->consistencyToken,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
