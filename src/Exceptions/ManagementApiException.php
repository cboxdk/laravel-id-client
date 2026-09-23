<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\Support\Claims;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * The environment management API refused, or could not be reached.
 *
 * `$error` is the API's stable machine code (`not_found`, `slug_taken`,
 * `insufficient_scope`, …) — branch on that, never on the message. `$status` is null when
 * there was no response at all. Specific outcomes have their own subclasses so the common
 * branches are a `catch`, not an `if`: {@see ResourceNotFound}, {@see ValidationFailed}.
 */
class ManagementApiException extends CboxIdException
{
    public ?string $error = null;

    public ?int $status = null;

    /** Seconds to wait, off `Retry-After` — set only on a 429. */
    public ?int $retryAfter = null;

    final public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unreachable(string $baseUrl, ?Throwable $previous = null): self
    {
        return new self("Could not reach the Cbox ID management API at {$baseUrl}.", $previous);
    }

    public static function fromResponse(string $action, Response $response): self
    {
        $body = Claims::normalize($response->json());
        $error = Claims::string($body, 'error');
        $message = Claims::string($body, 'message') ?? Claims::string($body, 'error_description');

        $class = match (true) {
            $response->status() === 404 => ResourceNotFound::class,
            $response->status() === 422 => ValidationFailed::class,
            default => self::class,
        };

        $exception = new $class($action.' failed: '.($message ?? $error ?? 'HTTP '.$response->status()));
        $exception->error = $error;
        $exception->status = $response->status();

        $retry = trim((string) $response->header('Retry-After'));
        $exception->retryAfter = $response->status() === 429 && ctype_digit($retry) ? (int) $retry : null;

        if ($exception instanceof ValidationFailed) {
            $exception->errors = ValidationFailed::parseErrors(Claims::object($body, 'errors'));
        }

        return $exception;
    }

    /** The key is missing, revoked, or for another environment's host. */
    public function isUnauthorized(): bool
    {
        return $this->status === 401;
    }

    /** The key is fine but lacks the scope this endpoint requires (e.g. `members:write`). */
    public function isForbidden(): bool
    {
        return $this->status === 403;
    }

    /** A precondition on the server's side: e.g. removing the last owner. */
    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
