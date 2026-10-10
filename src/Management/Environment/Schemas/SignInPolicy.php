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
        /** The environment-wide methods and session lengths actually in force, after the deployment's ceiling. */
        public SignInMethodsInForce $inForce,
        /** The deployment's ceiling: which methods it offers at all, the longest sessions it allows, and whether it has Turnstile keys. */
        public SignInMethodsInForce $deployment,
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
            inForce: Field::required($data, 'in_force', 'SignInPolicy', Value::dto(SignInMethodsInForce::fromArray(...))),
            deployment: Field::required($data, 'deployment', 'SignInPolicy', Value::dto(SignInMethodsInForce::fromArray(...))),
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
            'in_force' => $this->inForce->toArray(),
            'deployment' => $this->deployment->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
