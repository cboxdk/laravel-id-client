<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/** `#/components/schemas/AppSecret` on the environment plane. */
readonly class AppSecret implements JsonSerializable
{
    public function __construct(
        public string $id,
        /** The characters the secret ends in; null for one older than per-secret storage. */
        public ?string $hint = null,
        public ?string $createdAt = null,
        /** Set on a secret a rotation replaced — it stops working then. */
        public ?string $expiresAt = null,
        public ?string $lastUsedAt = null,
        /** Only on the rotation that minted it. Never again — null on an idempotent replay. */
        public ?string $clientSecret = null,
        /** On a rotation — when the secrets it replaced stop working. */
        public ?string $previousExpireAt = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'AppSecret', Value::string(...)),
            hint: Field::optional($data, 'hint', 'AppSecret', Value::string(...)),
            createdAt: Field::optional($data, 'created_at', 'AppSecret', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'AppSecret', Value::string(...)),
            lastUsedAt: Field::optional($data, 'last_used_at', 'AppSecret', Value::string(...)),
            clientSecret: Field::optional($data, 'client_secret', 'AppSecret', Value::string(...)),
            previousExpireAt: Field::optional($data, 'previous_expire_at', 'AppSecret', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'hint' => $this->hint,
            'created_at' => $this->createdAt,
            'expires_at' => $this->expiresAt,
            'last_used_at' => $this->lastUsedAt,
            'client_secret' => $this->clientSecret,
            'previous_expire_at' => $this->previousExpireAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
