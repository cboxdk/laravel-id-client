<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `FeatureFlagEvaluationEvaluationsItem` on the environment plane. */
readonly class FeatureFlagEvaluationEvaluationsItem implements JsonSerializable
{
    public function __construct(
        public string $key,
        public bool $enabled,
        /**
         * The rule that decided.
         * One of `disabled`, `user_target`, `organization_target`, `rollout`, `default`.
         */
        public string $reason,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Field::required($data, 'key', 'FeatureFlagEvaluationEvaluationsItem', Value::string(...)),
            enabled: Field::required($data, 'enabled', 'FeatureFlagEvaluationEvaluationsItem', Value::bool(...)),
            reason: Field::required($data, 'reason', 'FeatureFlagEvaluationEvaluationsItem', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'enabled' => $this->enabled,
            'reason' => $this->reason,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
