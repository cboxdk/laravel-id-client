<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/SignInMethodsInForce` on the environment plane. */
readonly class SignInMethodsInForce implements JsonSerializable
{
    public function __construct(
        public bool $passkeys,
        public bool $magicLink,
        /** 0 for no idle timeout. */
        public int $sessionIdleMinutes,
        public int $sessionAbsoluteMinutes,
        public bool $botChallenge,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            passkeys: Field::required($data, 'passkeys', 'SignInMethodsInForce', Value::bool(...)),
            magicLink: Field::required($data, 'magic_link', 'SignInMethodsInForce', Value::bool(...)),
            sessionIdleMinutes: Field::required($data, 'session_idle_minutes', 'SignInMethodsInForce', Value::int(...)),
            sessionAbsoluteMinutes: Field::required($data, 'session_absolute_minutes', 'SignInMethodsInForce', Value::int(...)),
            botChallenge: Field::required($data, 'bot_challenge', 'SignInMethodsInForce', Value::bool(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
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
