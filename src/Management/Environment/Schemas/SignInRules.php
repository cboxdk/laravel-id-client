<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SignInRules` on the environment plane. */
readonly class SignInRules implements JsonSerializable
{
    public function __construct(
        /** Minimum password length. */
        public int $minLength,
        /** Passwords found in known breaches are refused. */
        public bool $requireBreachCheck,
        /** Previous passwords that may not be reused. */
        public int $reuseHistory,
        /** One of `off`, `optional`, `required`. */
        public string $mfa,
        /**
         * `required` refuses every other way in.
         * One of `off`, `preferred`, `required`.
         */
        public string $sso,
        /** Days before a password must be changed; null for no limit. */
        public ?int $maxAgeDays = null,
        /** Failed attempts before a lockout; null for none. */
        public ?int $lockoutThreshold = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            minLength: Field::required($data, 'min_length', 'SignInRules', Value::int(...)),
            requireBreachCheck: Field::required($data, 'require_breach_check', 'SignInRules', Value::bool(...)),
            reuseHistory: Field::required($data, 'reuse_history', 'SignInRules', Value::int(...)),
            mfa: Field::required($data, 'mfa', 'SignInRules', Value::string(...)),
            sso: Field::required($data, 'sso', 'SignInRules', Value::string(...)),
            maxAgeDays: Field::optional($data, 'max_age_days', 'SignInRules', Value::int(...)),
            lockoutThreshold: Field::optional($data, 'lockout_threshold', 'SignInRules', Value::int(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'min_length' => $this->minLength,
            'require_breach_check' => $this->requireBreachCheck,
            'max_age_days' => $this->maxAgeDays,
            'reuse_history' => $this->reuseHistory,
            'mfa' => $this->mfa,
            'sso' => $this->sso,
            'lockout_threshold' => $this->lockoutThreshold,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
