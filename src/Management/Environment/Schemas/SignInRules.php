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
        /** Environment-wide. Whether passkeys may be used to sign in and be added. An organization's override never changes it. */
        public bool $passkeys,
        /** Environment-wide. Whether a one-time sign-in link may be emailed. */
        public bool $magicLink,
        /** Environment-wide. Whether a sign-up Radar flags is challenged with Turnstile, where the deployment has keys. */
        public bool $botChallenge,
        /** Days before a password must be changed; null for no limit. */
        public ?int $maxAgeDays = null,
        /** Failed attempts before a lockout; null for none. */
        public ?int $lockoutThreshold = null,
        /** Environment-wide. Minutes of inactivity before a session ends; null for the deployment's. */
        public ?int $sessionIdleMinutes = null,
        /** Environment-wide. Minutes a session lasts at most; null for the deployment's. */
        public ?int $sessionAbsoluteMinutes = null,
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
            passkeys: Field::required($data, 'passkeys', 'SignInRules', Value::bool(...)),
            magicLink: Field::required($data, 'magic_link', 'SignInRules', Value::bool(...)),
            botChallenge: Field::required($data, 'bot_challenge', 'SignInRules', Value::bool(...)),
            maxAgeDays: Field::optional($data, 'max_age_days', 'SignInRules', Value::int(...)),
            lockoutThreshold: Field::optional($data, 'lockout_threshold', 'SignInRules', Value::int(...)),
            sessionIdleMinutes: Field::optional($data, 'session_idle_minutes', 'SignInRules', Value::int(...)),
            sessionAbsoluteMinutes: Field::optional($data, 'session_absolute_minutes', 'SignInRules', Value::int(...)),
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
            'passkeys' => $this->passkeys,
            'magic_link' => $this->magicLink,
            'session_idle_minutes' => $this->sessionIdleMinutes,
            'session_absolute_minutes' => $this->sessionAbsoluteMinutes,
            'bot_challenge' => $this->botChallenge,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
