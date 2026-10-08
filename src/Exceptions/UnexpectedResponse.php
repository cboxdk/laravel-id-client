<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * A management plane answered successfully, but not in the shape its OpenAPI document
 * promises — a required field missing, or of another type.
 *
 * The request DID succeed: a write has happened. The typed result could not be built, so
 * this carries what is needed to carry on — the raw decoded `$body` and the idempotency
 * key, so repeating the call replays the same answer instead of acting twice. It means the
 * SDK and the server disagree about the contract: regenerate the client against the server
 * you run (`composer generate`), or report it.
 */
class UnexpectedResponse extends CboxIdException
{
    public mixed $body = null;

    public ?string $idempotencyKey = null;

    public static function at(string $where, string $expected, mixed $value): self
    {
        return new self("{$where}: expected {$expected}, got ".get_debug_type($value).'.');
    }
}
