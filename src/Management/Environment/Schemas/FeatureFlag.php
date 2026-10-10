<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A switch apps ask about per user and organization. First match wins: switched off, then a user rule, then an organization rule, then the rollout, then the default.
 *
 * `#/components/schemas/FeatureFlag` on the environment plane.
 */
readonly class FeatureFlag implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** What code asks for and the `feature_flags` claim carries. Fixed once created. */
        public string $key,
        /** The kill switch: false is off for everyone. */
        public bool $enabled,
        /** The answer when no rule matches. */
        public bool $defaultValue,
        /**
         * Rules for named users; they outrank every other rule.
         *
         * @var list<FeatureFlagRule>
         */
        public array $users,
        /**
         * Rules for named organizations; they outrank the rollout and the default.
         *
         * @var list<FeatureFlagRule>
         */
        public array $organizations,
        public ?string $description = null,
        /** On for this share of everyone else, by a stable hash of the user id (the organization id without one). */
        public ?int $rolloutPercentage = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'FeatureFlag', Value::string(...)),
            key: Field::required($data, 'key', 'FeatureFlag', Value::string(...)),
            enabled: Field::required($data, 'enabled', 'FeatureFlag', Value::bool(...)),
            defaultValue: Field::required($data, 'default_value', 'FeatureFlag', Value::bool(...)),
            users: Field::required($data, 'users', 'FeatureFlag', Value::list(Value::dto(FeatureFlagRule::fromArray(...)))),
            organizations: Field::required($data, 'organizations', 'FeatureFlag', Value::list(Value::dto(FeatureFlagRule::fromArray(...)))),
            description: Field::optional($data, 'description', 'FeatureFlag', Value::string(...)),
            rolloutPercentage: Field::optional($data, 'rollout_percentage', 'FeatureFlag', Value::int(...)),
            createdAt: Field::optional($data, 'created_at', 'FeatureFlag', Value::string(...)),
            updatedAt: Field::optional($data, 'updated_at', 'FeatureFlag', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'description' => $this->description,
            'enabled' => $this->enabled,
            'default_value' => $this->defaultValue,
            'rollout_percentage' => $this->rolloutPercentage,
            'users' => array_map(static fn (FeatureFlagRule $item) => $item->toArray(), $this->users),
            'organizations' => array_map(static fn (FeatureFlagRule $item) => $item->toArray(), $this->organizations),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
