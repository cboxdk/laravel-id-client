<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Illuminate\Http\Client\Response;

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
class AuthenticationFailed extends CboxIdException
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
     * The authorization server sent the browser back with `?error=` (RFC 6749 §4.1.2.1).
     *
     * The description used to be dropped here, so "invalid_scope" reached the log while
     * "this application is not registered for the requested scope(s): groups" did not —
     * and that sentence is the difference between reading a log and walking the OAuth
     * flow by hand. It is in the message, for the log, and on `$errorDescription`. It is
     * NOT end-user copy: it describes how this deployment is registered.
     *
     * `access_denied` is also what Cbox ID answers when `organization=` names an
     * organization the person is not an active member of.
     */
    public static function fromCallback(string $error, ?string $description = null): self
    {
        $description = $description !== null && $description !== '' ? $description : null;

        $exception = new self('Cbox ID returned an error: '.$error.($description !== null ? ' ('.$description.')' : ''));
        $exception->error = $error;
        $exception->errorDescription = $description;

        return $exception;
    }

    /** The person (or the authorization server on their behalf) declined. */
    public function isAccessDenied(): bool
    {
        return $this->error === 'access_denied';
    }

    /**
     * A `prompt=none` request could not complete silently — send the person through an
     * interactive sign-in instead (OIDC Core §3.1.2.6).
     */
    public function requiresInteraction(): bool
    {
        return in_array($this->error, ['login_required', 'consent_required', 'interaction_required', 'account_selection_required'], true);
    }

    /**
     * The refresh token is spent, revoked or replayed: the session is over and the
     * person has to sign in again. Retrying cannot succeed.
     */
    public function isInvalidGrant(): bool
    {
        return $this->error === 'invalid_grant';
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
