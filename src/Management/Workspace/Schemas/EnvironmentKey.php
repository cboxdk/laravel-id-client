<?php

declare(strict_types=1);

// GENERATED from openapi/workspace.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Workspace\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * A management key (`cbid_env_…`) of one of the workspace's environments. `token` is present only in the answer that minted it, and `null` on an idempotent replay.
 *
 * `#/components/schemas/EnvironmentKey` on the workspace plane.
 */
readonly class EnvironmentKey implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $environmentId,
        public string $name,
        /** @var list<string> */
        public array $scopes,
        public ?string $prefix = null,
        public ?string $expiresAt = null,
        /** The key's value. Shown once. */
        public ?string $token = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'EnvironmentKey', Value::string(...)),
            environmentId: Field::required($data, 'environment_id', 'EnvironmentKey', Value::string(...)),
            name: Field::required($data, 'name', 'EnvironmentKey', Value::string(...)),
            scopes: Field::required($data, 'scopes', 'EnvironmentKey', Value::list(Value::string(...))),
            prefix: Field::optional($data, 'prefix', 'EnvironmentKey', Value::string(...)),
            expiresAt: Field::optional($data, 'expires_at', 'EnvironmentKey', Value::string(...)),
            token: Field::optional($data, 'token', 'EnvironmentKey', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'environment_id' => $this->environmentId,
            'name' => $this->name,
            'prefix' => $this->prefix,
            'scopes' => $this->scopes,
            'expires_at' => $this->expiresAt,
            'token' => $this->token,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
