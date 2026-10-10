<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * Every flag's answer for the user and organization asked about.
 *
 * `#/components/schemas/FeatureFlagEvaluation` on the environment plane.
 */
readonly class FeatureFlagEvaluation implements JsonSerializable
{
    public function __construct(
        /**
         * The keys of the flags that are on, sorted — exactly what the token's `feature_flags` claim would carry.
         *
         * @var list<string>
         */
        public array $featureFlags,
        /** @var list<FeatureFlagEvaluationEvaluationsItem> */
        public array $evaluations,
        public ?string $userId = null,
        public ?string $organizationId = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            featureFlags: Field::required($data, 'feature_flags', 'FeatureFlagEvaluation', Value::list(Value::string(...))),
            evaluations: Field::required($data, 'evaluations', 'FeatureFlagEvaluation', Value::list(Value::dto(FeatureFlagEvaluationEvaluationsItem::fromArray(...)))),
            userId: Field::optional($data, 'user_id', 'FeatureFlagEvaluation', Value::string(...)),
            organizationId: Field::optional($data, 'organization_id', 'FeatureFlagEvaluation', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'organization_id' => $this->organizationId,
            'feature_flags' => $this->featureFlags,
            'evaluations' => array_map(static fn (FeatureFlagEvaluationEvaluationsItem $item) => $item->toArray(), $this->evaluations),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
