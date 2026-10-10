<?php

declare(strict_types=1);

// GENERATED from openapi/environment.yaml by bin/generate-management. Do not edit.

namespace Cbox\Id\Client\Management\Environment\Schemas;

use Cbox\Id\Client\Management\Transport\Field;
use Cbox\Id\Client\Management\Transport\Value;
use JsonSerializable;

/**
 * One person's connected account at a pipe's provider. Never a token.
 *
 * `#/components/schemas/PipeConnection` on the environment plane.
 */
readonly class PipeConnection implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $pipeId,
        public string $provider,
        public string $userId,
        /** One of `active`, `needs_reauth`. */
        public string $status,
        /** @var list<string> */
        public array $scopes,
        /** The account's name at the provider, when it says (a GitHub login, an email, a workspace). */
        public ?string $account = null,
        /** @var array<string, mixed> */
        public ?array $metadata = null,
        /** When the current access token expires. Null when it does not. */
        public ?string $expiresAt = null,
        public ?string $connectedAt = null,
        public ?string $lastRefreshedAt = null,
        /** Consecutive transient refresh failures. */
        public ?int $refreshFailures = null,
        public ?string $lastError = null,
        /** Why it needs reconnecting: the provider's error code, or `no_refresh_token`. */
        public ?string $reauthReason = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::required($data, 'id', 'PipeConnection', Value::string(...)),
            pipeId: Field::required($data, 'pipe_id', 'PipeConnection', Value::string(...)),
            provider: Field::required($data, 'provider', 'PipeConnection', Value::string(...)),
            userId: Field::required($data, 'user_id', 'PipeConnection', Value::string(...)),
            status: Field::required($data, 'status', 'PipeConnection', Value::string(...)),
            scopes: Field::required($data, 'scopes', 'PipeConnection', Value::list(Value::string(...))),
            account: Field::optional($data, 'account', 'PipeConnection', Value::string(...)),
            metadata: Field::optional($data, 'metadata', 'PipeConnection', Value::object(...)),
            expiresAt: Field::optional($data, 'expires_at', 'PipeConnection', Value::string(...)),
            connectedAt: Field::optional($data, 'connected_at', 'PipeConnection', Value::string(...)),
            lastRefreshedAt: Field::optional($data, 'last_refreshed_at', 'PipeConnection', Value::string(...)),
            refreshFailures: Field::optional($data, 'refresh_failures', 'PipeConnection', Value::int(...)),
            lastError: Field::optional($data, 'last_error', 'PipeConnection', Value::string(...)),
            reauthReason: Field::optional($data, 'reauth_reason', 'PipeConnection', Value::string(...)),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'pipe_id' => $this->pipeId,
            'provider' => $this->provider,
            'user_id' => $this->userId,
            'status' => $this->status,
            'account' => $this->account,
            'scopes' => $this->scopes,
            'metadata' => $this->metadata,
            'expires_at' => $this->expiresAt,
            'connected_at' => $this->connectedAt,
            'last_refreshed_at' => $this->lastRefreshedAt,
            'refresh_failures' => $this->refreshFailures,
            'last_error' => $this->lastError,
            'reauth_reason' => $this->reauthReason,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
