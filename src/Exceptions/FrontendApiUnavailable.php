<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The Frontend API could not be read.
 *
 * A class of its own rather than a configuration error, because the two need different
 * answers: a misconfigured key is a deploy to fix, and an unreachable issuer is a page to
 * degrade gracefully. `status` is the HTTP status when there was a response at all — null
 * means the request never got one.
 */
class FrontendApiUnavailable extends RuntimeException
{
    private function __construct(string $message, public readonly ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unreachable(string $issuer, ?Throwable $previous = null): self
    {
        return new self("Could not reach Cbox ID at {$issuer}.", null, $previous);
    }

    public static function refused(string $reason, int $status): self
    {
        return new self($reason, $status);
    }
}
