<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `SsoCertificatesChecksItem` on the environment plane. */
readonly class SsoCertificatesChecksItem implements JsonSerializable
{
    public function __construct(
        /** One of `readable`, `not_expired`, `key_strength`, `not_on_file`, `valid_now`. */
        public string $check,
        public bool $passed,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            check: Field::required($data, 'check', 'SsoCertificatesChecksItem', Value::string(...)),
            passed: Field::required($data, 'passed', 'SsoCertificatesChecksItem', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'check' => $this->check,
            'passed' => $this->passed,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
