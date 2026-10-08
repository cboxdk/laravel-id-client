<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Management\Transport;

/**
 * A successful answer from a management plane.
 *
 * `$data` is the envelope's `data`, typed by the generated method that returned it — a
 * schema object, a list of them, an array for a free-form object, or null for a
 * `204 No Content`. `$body` is the whole decoded document.
 *
 * @template-covariant T
 */
readonly class ApiResponse
{
    /**
     * @param  T  $data
     * @param  array<string, mixed>  $meta  the envelope's `meta`, when it has one
     * @param  mixed  $body  the whole decoded JSON document (arrays, as `json_decode(…, true)` gives them)
     * @param  bool  $replayed  true when the server answered from its idempotency store (`Idempotent-Replayed: true`):
     *                          this is the FIRST request's answer, and a secret it carried (a client secret, a key's
     *                          token) is null — it was shown once, to the request that created it
     * @param  string|null  $idempotencyKey  the `Idempotency-Key` a write was sent with
     * @param  string|null  $requestId  the id the server served the request under (`X-Request-Id`)
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public mixed $data,
        public int $status = 200,
        public array $meta = [],
        public mixed $body = null,
        public bool $replayed = false,
        public ?string $idempotencyKey = null,
        public ?string $requestId = null,
        public array $headers = [],
    ) {}

    /** One response header (the first value), case-insensitively; null when absent. */
    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return $values[0] ?? null;
            }
        }

        return null;
    }
}
