<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * A backchannel (CIBA) or device grant ended without tokens.
 *
 * `$error` is the OAuth error code Cbox ID answered with (`invalid_scope`,
 * `unauthorized_client`, …) or `server_error` when the answer was not a valid OAuth
 * error at all. The named subclasses cover the codes a caller usually handles
 * differently; catch this one for the rest.
 */
class GrantFailed extends CboxIdException
{
    public function __construct(
        string $message,
        public readonly string $error = 'server_error',
        public readonly ?string $description = null,
        public readonly int $status = 0,
    ) {
        parent::__construct($message);
    }
}
