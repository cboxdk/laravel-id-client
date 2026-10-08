<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Cbox\Id\Client\Management\EnvironmentClient;

/**
 * A management plane answered with an error.
 *
 * Every plane answers a failure with the same envelope — `{error, message}`, plus a
 * field-keyed `errors` map on `validation_failed` — so this one class covers all four
 * typed clients ({@see EnvironmentClient} and its siblings).
 * Branch on `$error`, the stable machine code; never on the message, which is prose and
 * is reworded freely.
 *
 * The REQUEST body is never part of the exception: management writes carry secrets (a
 * password you set, a key's scopes, a webhook secret) and applications log exceptions.
 */
class CboxIdApiException extends CboxIdException
{
    /**
     * @param  int  $status  the HTTP status
     * @param  string  $error  the stable machine code, e.g. `validation_failed`, `slug_taken`, `not_found`
     * @param  array<string, list<string>>  $errors  on `validation_failed` only: each offending field and its messages
     * @param  string|null  $requestId  the id the server served the request under (the body's `request_id`, else `X-Request-Id`) — quote it when reporting a problem
     * @param  int|null  $retryAfter  seconds to wait, off `Retry-After` — set on a 429 or 503 the client gave up retrying
     */
    public function __construct(
        public readonly int $status,
        public readonly string $error,
        string $message,
        public readonly array $errors = [],
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status);
    }

    /** A `422 validation_failed`, with field errors in `$errors`. */
    public function isValidationError(): bool
    {
        return $this->error === 'validation_failed';
    }

    /** The resource does not exist — or is not visible to this credential. */
    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    /** The credential is missing, revoked, expired, or for another host or plane. */
    public function isUnauthorized(): bool
    {
        return $this->status === 401;
    }

    /** The credential is fine but lacks the scope (or role) this operation needs. */
    public function isForbidden(): bool
    {
        return $this->status === 403;
    }

    /** The request conflicts with the resource's state: `last_owner`, `already_member`, … */
    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
