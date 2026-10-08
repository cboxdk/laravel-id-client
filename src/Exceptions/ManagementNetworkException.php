<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

use Throwable;

/**
 * A management request never got an answer — DNS, TLS, a reset connection, a timeout —
 * after every retry the client was allowed.
 *
 * A write may or may not have happened. Repeating it with the same idempotency key
 * (`$idempotencyKey`, through `new CallOptions(idempotencyKey: …)`) is safe and tells you
 * which: the server answers the first request's outcome rather than running it twice.
 */
class ManagementNetworkException extends CboxIdException
{
    public function __construct(
        string $message,
        /** The `Idempotency-Key` the write was sent with, to repeat it safely. Null for a read. */
        public readonly ?string $idempotencyKey = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
