<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

/**
 * A started CIBA request (OpenID CIBA Core §7.3), waiting for the person to answer.
 *
 * Pass it to `poll()` or `waitForToken()`. It carries the `resource` the request was
 * started with, because the token request must name the same one. Store it between
 * HTTP requests with {@see toArray()} / {@see fromArray()}.
 */
readonly class PendingBackchannelAuthentication
{
    public function __construct(
        public string $authReqId = '',
        public int $expiresIn = 0,
        public int $interval = 5,
        public int $expiresAt = 0,
        public ?string $resource = null,
    ) {}

    /** @return array{auth_req_id: string, expires_in: int, interval: int, expires_at: int, resource: ?string} */
    public function toArray(): array
    {
        return [
            'auth_req_id' => $this->authReqId,
            'expires_in' => $this->expiresIn,
            'interval' => $this->interval,
            'expires_at' => $this->expiresAt,
            'resource' => $this->resource,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            is_string($data['auth_req_id'] ?? null) ? $data['auth_req_id'] : '',
            is_int($data['expires_in'] ?? null) ? $data['expires_in'] : 0,
            is_int($data['interval'] ?? null) ? $data['interval'] : 5,
            is_int($data['expires_at'] ?? null) ? $data['expires_at'] : 0,
            is_string($data['resource'] ?? null) ? $data['resource'] : null,
        );
    }
}
