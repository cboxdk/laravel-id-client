<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\IdentityClient;
use Illuminate\Http\Client\Response;

/**
 * A Pipes token lease was refused. Catch the subclasses for the cases you can act on:
 *
 * - {@see PipeNotConnected} (404) and {@see PipeReauthorizationRequired} (409) — send the
 *   person to `$connectUrl` ({@see connectUrlWith()} adds your app and a way back).
 * - {@see PipeTemporarilyUnavailable} (503) — retry after `$retryAfter` seconds.
 * - {@see PipeLeaseDenied} (403) — a configuration problem: the app is not granted the
 *   pipe, the pipe is disabled, or the person is outside the app's organization.
 *
 * Anything else (401, 422, 429) is this class itself, with `$status` and `$error`.
 */
class PipeLeaseFailed extends CboxIdException
{
    final public function __construct(
        string $message,
        /** The server's error code, e.g. `not_connected`. */
        public readonly ?string $error,
        public readonly int $status,
        /** Where to send the person to (re)connect — set on 404 and 409. */
        public readonly ?string $connectUrl = null,
        /** Seconds to wait, off `Retry-After`, when the server sent one. */
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /** The refusal a failed lease response describes, as the most specific class. */
    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $status = $response->status();
        $error = is_string($body['error'] ?? null) ? $body['error'] : null;
        $message = is_string($body['message'] ?? null) && $body['message'] !== ''
            ? $body['message']
            : "The pipe lease failed with status {$status}.";
        $connectUrl = is_string($body['connect_url'] ?? null) && $body['connect_url'] !== '' ? $body['connect_url'] : null;
        $header = trim($response->header('Retry-After'));
        $retryAfter = ctype_digit($header) ? (int) $header : null;

        $class = match (true) {
            $status === 404 && $connectUrl !== null => PipeNotConnected::class,
            $status === 409 && $connectUrl !== null => PipeReauthorizationRequired::class,
            $status === 503 => PipeTemporarilyUnavailable::class,
            $status === 403 && $error !== 'insufficient_scope' => PipeLeaseDenied::class,
            default => self::class,
        };

        return new $class($message, $error, $status, $connectUrl, $retryAfter);
    }

    /**
     * `$connectUrl` with your app's `client_id` and a `return_to` on it, or null when the
     * refusal carries none. See {@see IdentityClient::pipeConnectUrl()}.
     */
    public function connectUrlWith(?string $clientId = null, ?string $returnTo = null): ?string
    {
        return $this->connectUrl === null ? null : IdentityClient::withConnectReturn($this->connectUrl, $clientId, $returnTo);
    }
}
