<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SignInPolicy` on the environment plane. */
readonly class SignInPolicy implements JsonSerializable
{
    public function __construct(
        /** What is in force at this level: the baseline, or the organization's override tightened over it. */
        public SignInRules $policy,
        public SignInRules $baseline,
        /** True for an organization with no override of its own. */
        public bool $inheriting,
        /** null for the environment baseline. */
        public ?string $organizationId = null,
        /** What the organization stored itself; null when it inherits (and always for the baseline). */
        public ?SignInRules $override = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            policy: Field::required($data, 'policy', 'SignInPolicy', Value::dto(SignInRules::fromArray(...))),
            baseline: Field::required($data, 'baseline', 'SignInPolicy', Value::dto(SignInRules::fromArray(...))),
            inheriting: Field::required($data, 'inheriting', 'SignInPolicy', Value::bool(...)),
            organizationId: Field::optional($data, 'organization_id', 'SignInPolicy', Value::string(...)),
            override: Field::optional($data, 'override', 'SignInPolicy', Value::dto(SignInRules::fromArray(...))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'policy' => $this->policy->toArray(),
            'baseline' => $this->baseline->toArray(),
            'override' => $this->override?->toArray(),
            'inheriting' => $this->inheriting,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
