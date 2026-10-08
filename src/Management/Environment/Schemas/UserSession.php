<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/UserSession` on the environment plane. */
readonly class UserSession implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $expiresAt,
        /** Somebody else opened it as this person. */
        public bool $impersonation,
        public ?string $userAgent = null,
        public ?string $ip = null,
        public ?string $lastActiveAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'UserSession', Value::string(...)),
            expiresAt: Field::required($data, 'expires_at', 'UserSession', Value::string(...)),
            impersonation: Field::required($data, 'impersonation', 'UserSession', Value::bool(...)),
            userAgent: Field::optional($data, 'user_agent', 'UserSession', Value::string(...)),
            ip: Field::optional($data, 'ip', 'UserSession', Value::string(...)),
            lastActiveAt: Field::optional($data, 'last_active_at', 'UserSession', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_agent' => $this->userAgent,
            'ip' => $this->ip,
            'last_active_at' => $this->lastActiveAt,
            'expires_at' => $this->expiresAt,
            'impersonation' => $this->impersonation,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
