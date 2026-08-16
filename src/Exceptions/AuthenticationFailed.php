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

        return $exception;
    }
}
