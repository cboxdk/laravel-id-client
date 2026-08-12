<?php

declare(strict_types=1);

namespace Cbox\Id\Client\ValueObjects;

use Cbox\Id\Client\Frontend\FrontendClient;

/**
 * Who is signed in, or nobody.
 *
 * `user` is null for signed-out, which is a state rather than an error — see
 * {@see FrontendClient::session()}.
 */
readonly class FrontendSession
{
    public function __construct(public ?FrontendUser $user) {}

    /** @param array<string, mixed> $document */
    public static function fromArray(array $document): self
    {
        $user = $document['user'] ?? null;

        if (! is_array($user)) {
            return new self(null);
        }

        $document = [];

        foreach ($user as $key => $value) {
            if (is_string($key)) {
                $document[$key] = $value;
            }
        }

        return new self(FrontendUser::fromArray($document));
    }

    public function signedIn(): bool
    {
        return $this->user !== null;
    }
}
