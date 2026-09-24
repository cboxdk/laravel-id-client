<?php

declare(strict_types=1);

namespace Cbox\Id\Client\Exceptions;

/**
 * A customer API key was not accepted.
 *
 * Two rejections, as with {@see TokenRejected}, and they want different answers: a key
 * that is not live (unknown, revoked, expired, for another application) is a 401 — use
 * another key — while a live key that lacks a permission is a 403 — ask its owner for a
 * broader one. Which one happened is a fact about the key, so it is carried here.
 */
class ApiKeyRejected extends CboxIdException
{
    /**
     * @param  list<string>  $missingPermissions  empty when the key itself is not live
     */
    private function __construct(string $message, public readonly array $missingPermissions = [])
    {
        parent::__construct($message);
    }

    public static function inactive(string $reason = 'The API key is not active.'): self
    {
        return new self($reason);
    }

    /** @param list<string> $missing */
    public static function missingPermissions(array $missing): self
    {
        return new self('The API key is missing required permission(s): '.implode(', ', $missing), $missing);
    }

    public function isInsufficientPermission(): bool
    {
        return $this->missingPermissions !== [];
    }
}
