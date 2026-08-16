<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Login could not be completed.
 *
 * `$error` is the RFC 6749 §5.2 code the instance sent, when it sent one. It used to be
 * discarded at every back-channel boundary, leaving one message string for outcomes that
 * demand opposite responses: `invalid_grant` on a refresh means the session is over and
 * the person has to sign in again, while a 503 means the same token is still good in a
 * moment. Code reduced to matching on prose either retries what can never succeed, or
 * signs out somebody who did not need to be.
 */
class AuthenticationFailed extends RuntimeException
{
    public ?string $error = null;

    public ?string $errorDescription = null;

    public ?int $status = null;

    /**
     * Seconds to wait, off the `Retry-After` header — set only on a 429.
     *
     * A 429 is the ONLY back-channel failure where the same request succeeds unchanged if
     * you wait; every other one needs a different request or a new sign-in. The limiter
     * says how long and this SDK dropped the header, so a caller with a retry loop
     * hammered a server that was already telling it to stop.
     */
    public ?int $retryAfter = null;

    /** Whether waiting and repeating the same request unchanged is worth it. */
    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * Build from a failed back-channel response, keeping whatever the instance said.
     *
     * Best-effort by design — a 502 from a proxy is HTML and a captive portal is worse,
     * and the caller still needs an exception rather than a parse error. What it must
     * never do is invent a code: an absent or unparseable `error` stays null, so
     * `$e->error === 'invalid_grant'` is true only because the instance said so.
     */
    public static function fromResponse(string $reason, Response $response): self
    {
        $error = null;
        $description = null;

        /** @var mixed $body */
        $body = $response->json();

        if (is_array($body)) {
            $error = is_string($body['error'] ?? null) ? $body['error'] : null;
            $description = is_string($body['error_description'] ?? null) ? $body['error_description'] : null;
        }

        $detail = $error ?? 'HTTP '.$response->status();

        $exception = new self($reason.': '.$detail);
        $exception->error = $error;
        $exception->errorDescription = $description;
        $exception->status = $response->status();

        // Seconds only. The HTTP-date form is legal per RFC 9110 and deliberately not
        // parsed: guessing at clock skew is worse than saying nothing, and `status === 429`
        // still tells the caller to back off.
        $header = trim((string) $response->header('Retry-After'));
        $exception->retryAfter = $header !== '' && ctype_digit($header) ? (int) $header : null;

        return $exception;
    }
}
