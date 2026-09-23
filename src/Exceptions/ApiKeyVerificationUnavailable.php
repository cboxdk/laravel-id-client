<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Throwable;

/**
 * Cbox ID could not say whether an API key is good.
 *
 * NOT a rejection. Reading "the identity provider is down" as "this key is invalid" tells
 * a customer their working key is broken, and they rotate it — or worse, stop trusting
 * every 401 you send. The middleware answers this with a 503 and `Retry-After`, and the
 * key is never accepted on the strength of a failed check.
 */
class ApiKeyVerificationUnavailable extends CboxIdException
{
    private function __construct(string $message, public readonly ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unreachable(?Throwable $previous = null): self
    {
        return new self('Could not reach Cbox ID to verify the API key.', null, $previous);
    }

    public static function refused(int $status, ?string $error = null): self
    {
        return new self('Cbox ID refused the API key verification request: '.($error ?? 'HTTP '.$status), $status);
    }
}
